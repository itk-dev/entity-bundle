<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ThirdPartyOverrideTest extends TestCase
{
    public function testAuditEntitiesConfig_AddsThirdPartyClass(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer([
            'user_class' => 'App\\Entity\\User',
            'audit' => [
                'enabled' => true,
                'entities' => ['Vendor\\Bundle\\Entity\\Thing'],
            ],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ]));

        $entities = $container->getExtensionConfig('dh_auditor')[0]['providers']['doctrine']['entities'];
        self::assertArrayHasKey('Vendor\\Bundle\\Entity\\Thing', $entities);
        self::assertNull($entities['Vendor\\Bundle\\Entity\\Thing']);
    }

    public function testAuditIgnoredColumnsConfig_AppliesToThirdPartyClass(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer([
            'user_class' => 'App\\Entity\\User',
            'audit' => [
                'enabled' => true,
                'entities' => ['Vendor\\Bundle\\Entity\\Thing'],
                'ignored_columns' => [
                    'Vendor\\Bundle\\Entity\\Thing' => ['password', 'token'],
                ],
            ],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ]));

        $entities = $container->getExtensionConfig('dh_auditor')[0]['providers']['doctrine']['entities'];
        self::assertSame(
            ['ignored_columns' => ['password', 'token']],
            $entities['Vendor\\Bundle\\Entity\\Thing'],
        );
    }

    public function testAuditIgnoredColumnsConfig_MergesWithAttributeDiscovery(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer([
            'user_class' => 'App\\Entity\\User',
            'audit' => [
                'enabled' => true,
                'ignored_columns' => [
                    FixtureEntity::class => ['label'],
                ],
            ],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ]));

        $entities = $container->getExtensionConfig('dh_auditor')[0]['providers']['doctrine']['entities'];
        // 'secret' from #[AuditIgnore], 'label' from config — both present, deduped.
        self::assertSame(['ignored_columns' => ['secret', 'label']], $entities[FixtureEntity::class]);
    }

    public function testAnonymizationRulesConfig_AddsRulesForThirdPartyClass(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'anonymization' => [
                'enabled' => true,
                'rules' => [
                    'Vendor\\Bundle\\Entity\\Thing' => [
                        'email' => ['strategy' => 'pseudonymize'],
                        'phone' => ['strategy' => 'redact', 'replacement' => '[X]'],
                    ],
                ],
            ],
        ]], $container = $this->container());

        $rules = $container->getParameter('itk_dev_entity.anonymization_rules');
        self::assertArrayHasKey('Vendor\\Bundle\\Entity\\Thing', $rules);
        self::assertSame(
            [
                ['property' => 'email', 'strategy' => 'pseudonymize', 'replacement' => null],
                ['property' => 'phone', 'strategy' => 'redact', 'replacement' => '[X]'],
            ],
            $rules['Vendor\\Bundle\\Entity\\Thing'],
        );
    }

    public function testAnonymizationRulesConfig_OverridesAttributeForSameProperty(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'anonymization' => [
                'enabled' => true,
                'rules' => [
                    FixtureEntity::class => [
                        'label' => ['strategy' => 'null'],
                    ],
                ],
            ],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ]], $container = $this->container());

        $rules = $container->getParameter('itk_dev_entity.anonymization_rules');
        $fixtureRules = $rules[FixtureEntity::class] ?? [];
        $byProp = [];
        foreach ($fixtureRules as $r) {
            $byProp[$r['property']] = $r['strategy'];
        }
        // #[Anonymize(strategy: Strategy::Redact)] on label is overridden by config 'null'.
        self::assertSame('null', $byProp['label']);
    }

    public function testAnonymizationRulesConfig_RejectsMalformedSpec(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'anonymization' => [
                'enabled' => true,
                'rules' => [
                    'Vendor\\Bundle\\Entity\\Thing' => [
                        'email' => 'pseudonymize', // bare string instead of { strategy: ... }
                    ],
                ],
            ],
        ]], $this->container());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 3));

        return $container;
    }

    private function prependContainer(array $config): ContainerBuilder
    {
        $container = $this->container();
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', $config);

        return $container;
    }
}
