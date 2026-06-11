<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Doctrine\Listener\SoftDeleteListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class SoftDeleteConfigTest extends TestCase
{
    public function testDefaultsToDisabled_NoListenerService_NoDoctrineFilter(): void
    {
        $container = $this->container();
        $config = ['user_class' => 'App\\Entity\\User'];

        $extension = new ITKDevEntityExtension();
        $extension->load([$config], $container);

        self::assertTrue(
            $this->isListenerExcluded($container),
            'SoftDeleteListener must be marked container.excluded when soft_delete is disabled',
        );

        $extension->prepend($prepend = $this->prependContainer($config));
        $doctrine = $prepend->getExtensionConfig('doctrine');
        $filters = $doctrine[0]['orm']['filters'] ?? [];
        self::assertArrayNotHasKey('soft_delete', $filters, 'soft_delete Doctrine filter must not be prepended when disabled');
    }

    public function testExplicitlyEnabled_RegistersListenerAndFilter(): void
    {
        $container = $this->container();
        $config = [
            'user_class' => 'App\\Entity\\User',
            'soft_delete' => ['enabled' => true],
        ];

        $extension = new ITKDevEntityExtension();
        $extension->load([$config], $container);

        self::assertTrue($container->hasDefinition(SoftDeleteListener::class));
        self::assertFalse($this->isListenerExcluded($container), 'SoftDeleteListener must not be excluded when soft_delete is enabled');

        $definition = $container->getDefinition(SoftDeleteListener::class);
        self::assertInstanceOf(Definition::class, $definition);
        $tags = $definition->getTag('doctrine.event_listener');
        self::assertNotEmpty($tags, 'SoftDeleteListener must be tagged as a Doctrine event listener');
        self::assertSame('onFlush', $tags[0]['event'] ?? null);

        $extension->prepend($prepend = $this->prependContainer($config));
        $doctrine = $prepend->getExtensionConfig('doctrine');
        $filters = $doctrine[0]['orm']['filters'] ?? [];
        self::assertArrayHasKey('soft_delete', $filters);
        self::assertTrue($filters['soft_delete']['enabled']);
    }

    private function isListenerExcluded(ContainerBuilder $container): bool
    {
        if (!$container->hasDefinition(SoftDeleteListener::class)) {
            return true;
        }

        return $container->getDefinition(SoftDeleteListener::class)->hasTag('container.excluded');
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

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
