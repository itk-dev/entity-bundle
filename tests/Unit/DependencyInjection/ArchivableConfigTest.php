<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ArchivableConfigTest extends TestCase
{
    public function testDefaultsToDisabledNoDoctrineFilter(): void
    {
        $config = ['user_class' => 'App\\Entity\\User'];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer($config));

        $doctrine = $prepend->getExtensionConfig('doctrine');
        $filters = $doctrine[0]['orm']['filters'] ?? [];
        self::assertArrayNotHasKey('archivable', $filters, 'archivable Doctrine filter must not be prepended when disabled');
    }

    public function testExplicitlyEnabledRegistersFilterEnabledByDefault(): void
    {
        $config = [
            'user_class' => 'App\\Entity\\User',
            'archivable' => ['enabled' => true],
        ];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer($config));

        $doctrine = $prepend->getExtensionConfig('doctrine');
        $filters = $doctrine[0]['orm']['filters'] ?? [];
        self::assertArrayHasKey('archivable', $filters);
        self::assertTrue($filters['archivable']['enabled'], 'archivable filter is registered enabled so archived rows are hidden by default; disable per-request to reveal');
    }

    /** @param array<string, mixed> $config */
    private function prependContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', $config);

        return $container;
    }
}
