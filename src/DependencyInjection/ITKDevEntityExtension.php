<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\DependencyInjection;

use Doctrine\ORM\Events;
use ITKDev\EntityBundle\Attribute\ITKDevEntity;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use ITKDev\EntityBundle\Audit\Attribute\AuditIgnore;
use ITKDev\EntityBundle\Doctrine\Filter\ArchivableFilter;
use ITKDev\EntityBundle\Doctrine\Filter\SoftDeleteFilter;
use ITKDev\EntityBundle\Doctrine\Listener\BlameableListener;
use ITKDev\EntityBundle\Doctrine\Listener\SoftDeleteListener;
use ITKDev\EntityBundle\Doctrine\Listener\TimestampableListener;
use ITKDev\EntityBundle\Privacy\Attribute\Anonymize;
use ITKDev\EntityBundle\Privacy\Strategy;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Security\Core\User\UserInterface;

final class ITKDevEntityExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $requiresUserClass = $config['audit']['enabled'] || $config['blameable']['enabled'];
        if ($requiresUserClass && (null === $config['user_class'] || '' === $config['user_class'])) {
            throw new InvalidConfigurationException('itk_dev_entity.user_class is required when audit or blameable is enabled.');
        }

        $container->setParameter('itk_dev_entity.user_class', $config['user_class'] ?? '');
        $container->setParameter('itk_dev_entity.entity_paths', $config['entity_paths']);
        $container->setParameter('itk_dev_entity.audit_retention', $config['audit']['retention']);
        $container->setParameter('itk_dev_entity.audit_retention_overrides', $config['audit']['retention_overrides']);

        if ($config['anonymization']['enabled']) {
            $entities = $this->discoverEntities($container, $config['entity_paths']);
            $rules = $this->discoverAnonymizationRules($entities);
            $rules = $this->mergeAnonymizationRules($rules, $config['anonymization']['rules'] ?? []);
            $container->setParameter('itk_dev_entity.anonymization_rules', $rules);
        } else {
            $container->setParameter('itk_dev_entity.anonymization_rules', []);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        if ($config['anonymization']['enabled']) {
            $loader->load('services_privacy.yaml');
        }

        if ($config['soft_delete']['enabled']) {
            $listener = (new Definition(SoftDeleteListener::class))
                ->setArguments([new Reference('clock')])
                ->addTag('doctrine.event_listener', ['event' => Events::onFlush]);
            $container->setDefinition(SoftDeleteListener::class, $listener);
        }

        if ($config['timestampable']['enabled']) {
            $listener = (new Definition(TimestampableListener::class))
                ->setArguments([new Reference('clock')])
                ->addTag('doctrine.event_listener', ['event' => Events::onFlush]);
            $container->setDefinition(TimestampableListener::class, $listener);
        }

        if ($config['blameable']['enabled']) {
            $listener = (new Definition(BlameableListener::class))
                ->setArguments([new Reference('security.helper')])
                ->addTag('doctrine.event_listener', ['event' => Events::onFlush]);
            $container->setDefinition(BlameableListener::class, $listener);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig('itk_dev_entity'));

        $filters = [];
        if ($config['archivable']['enabled']) {
            $filters['archivable'] = [
                'class' => ArchivableFilter::class,
                'enabled' => true,
            ];
        }
        if ($config['soft_delete']['enabled']) {
            $filters['soft_delete'] = [
                'class' => SoftDeleteFilter::class,
                'enabled' => true,
            ];
        }

        $ormConfig = ['filters' => $filters];
        if (null !== $config['user_class'] && '' !== $config['user_class']) {
            $ormConfig['resolve_target_entities'] = [
                UserInterface::class => $config['user_class'],
            ];
        }

        $container->prependExtensionConfig('doctrine', ['orm' => $ormConfig]);

        if (!$config['audit']['enabled']) {
            return;
        }

        $entities = $this->discoverEntities($container, $config['entity_paths']);
        $auditable = array_values(array_filter(
            $entities,
            static fn (string $class): bool => [] !== (new \ReflectionClass($class))->getAttributes(Auditable::class),
        ));
        foreach ($config['audit']['entities'] as $extra) {
            if (!\in_array($extra, $auditable, true)) {
                $auditable[] = $extra;
            }
        }
        if ([] === $auditable) {
            return;
        }

        $configIgnored = $config['audit']['ignored_columns'] ?? [];
        $entityConfig = [];
        foreach ($auditable as $class) {
            $ignored = $this->discoverAuditIgnoredColumns($class);
            foreach ($configIgnored[$class] ?? [] as $prop) {
                if (!\in_array($prop, $ignored, true)) {
                    $ignored[] = $prop;
                }
            }
            $entityConfig[$class] = [] === $ignored ? null : ['ignored_columns' => $ignored];
        }

        $container->prependExtensionConfig('dh_auditor', [
            'providers' => [
                'doctrine' => [
                    'table_suffix' => '_audit',
                    'entities' => $entityConfig,
                ],
            ],
        ]);
    }

    /**
     * Merge config-supplied anonymization rules into the attribute-discovered set.
     * For the same property name, the config wins (it's the explicit override).
     *
     * $configRules comes from a variableNode in the config tree, so its shape is
     * not enforced upstream — every layer is validated here. Class keys are not
     * required to exist locally (the bundle supports overrides for third-party
     * entities that may live in unloaded namespaces).
     *
     * @param array<class-string, list<array{property: string, strategy: string, replacement: ?string}>> $discovered
     * @param array<string, mixed>                                                                       $configRules
     *
     * @return array<string, list<array{property: string, strategy: string, replacement: ?string}>>
     */
    private function mergeAnonymizationRules(array $discovered, array $configRules): array
    {
        /** @var array<string, list<array{property: string, strategy: string, replacement: ?string}>> $merged */
        $merged = $discovered;

        foreach ($configRules as $class => $propRules) {
            if (!\is_array($propRules)) {
                throw new InvalidConfigurationException(sprintf('itk_dev_entity.anonymization.rules[%s] must be a map of property => { strategy: ..., replacement?: ... }.', $class));
            }

            /** @var array<string, array{property: string, strategy: string, replacement: ?string}> $byProp */
            $byProp = [];
            foreach ($merged[$class] ?? [] as $rule) {
                $byProp[$rule['property']] = $rule;
            }
            foreach ($propRules as $property => $spec) {
                if (!\is_string($property) || !\is_array($spec) || !isset($spec['strategy']) || !\is_string($spec['strategy'])) {
                    throw new InvalidConfigurationException(sprintf('itk_dev_entity.anonymization.rules[%s][%s] must be { strategy: ..., replacement?: ... }', $class, (string) $property));
                }
                if (null === Strategy::tryFrom($spec['strategy'])) {
                    $valid = implode(', ', array_map(static fn (Strategy $s): string => $s->value, Strategy::cases()));
                    throw new InvalidConfigurationException(sprintf('itk_dev_entity.anonymization.rules[%s][%s].strategy must be one of: %s. Got "%s".', $class, $property, $valid, $spec['strategy']));
                }
                $replacement = $spec['replacement'] ?? null;
                if (null !== $replacement && !\is_string($replacement)) {
                    throw new InvalidConfigurationException(sprintf('itk_dev_entity.anonymization.rules[%s][%s].replacement must be a string or null.', $class, $property));
                }
                $byProp[$property] = [
                    'property' => $property,
                    'strategy' => $spec['strategy'],
                    'replacement' => $replacement,
                ];
            }
            $merged[$class] = array_values($byProp);
        }

        return $merged;
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function discoverAuditIgnoredColumns(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }

        $ignored = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $prop) {
            if ([] !== $prop->getAttributes(AuditIgnore::class)) {
                $ignored[] = $prop->getName();
            }
        }

        return $ignored;
    }

    /**
     * @param list<string> $paths
     *
     * @return list<class-string>
     */
    private function discoverEntities(ContainerBuilder $container, array $paths): array
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        $entities = [];
        foreach ($paths as $path) {
            $resolved = str_replace('%kernel.project_dir%', $projectDir, $path);
            if (!is_dir($resolved)) {
                continue;
            }
            $container->addResource(new DirectoryResource($resolved, '/\.php$/'));

            foreach ((new Finder())->files()->in($resolved)->name('*.php') as $file) {
                $class = $this->resolveClassNameFromFile($file->getRealPath());
                if (null === $class || !class_exists($class)) {
                    continue;
                }
                $ref = new \ReflectionClass($class);
                if ($ref->isAbstract() || !$this->hasITKDevEntityAttribute($ref)) {
                    continue;
                }
                $entities[] = $class;
            }
        }

        sort($entities);

        return $entities;
    }

    /**
     * Walk the class hierarchy looking for #[ITKDevEntity]. The attribute is non-inherited
     * at the language level, but the bundle treats inherited declarations as opting in —
     * so subclasses of AbstractITKDevEntity automatically count.
     *
     * @param \ReflectionClass<object> $ref
     */
    private function hasITKDevEntityAttribute(\ReflectionClass $ref): bool
    {
        for ($cur = $ref; false !== $cur; $cur = $cur->getParentClass()) {
            if ([] !== $cur->getAttributes(ITKDevEntity::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the namespace + class declaration straight from the source so we don't need
     * to know the PSR-4 prefix for every configured entity_paths entry.
     */
    private function resolveClassNameFromFile(string $file): ?string
    {
        $src = @file_get_contents($file);
        if (false === $src) {
            return null;
        }
        if (!preg_match('/^namespace\s+([^;]+);/m', $src, $nsMatch)) {
            return null;
        }
        if (!preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $src, $classMatch)) {
            return null;
        }

        return trim($nsMatch[1]).'\\'.$classMatch[1];
    }

    /**
     * @param list<class-string> $entities
     *
     * @return array<class-string, list<array{property: string, strategy: string, replacement: ?string}>>
     */
    private function discoverAnonymizationRules(array $entities): array
    {
        $rules = [];
        foreach ($entities as $class) {
            $ref = new \ReflectionClass($class);
            $classRules = [];
            foreach ($ref->getProperties() as $prop) {
                $attrs = $prop->getAttributes(Anonymize::class);
                if ([] === $attrs) {
                    continue;
                }
                $attr = $attrs[0]->newInstance();
                $classRules[] = [
                    'property' => $prop->getName(),
                    'strategy' => $attr->strategy->value,
                    'replacement' => $attr->replacement,
                ];
            }
            if ([] !== $classRules) {
                $rules[$class] = $classRules;
            }
        }

        return $rules;
    }
}
