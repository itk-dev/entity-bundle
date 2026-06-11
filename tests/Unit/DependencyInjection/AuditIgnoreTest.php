<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AuditIgnoreTest extends TestCase
{
    public function testIgnoredColumnsArePrependedPerEntity(): void
    {
        $config = [
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer($config));

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        self::assertArrayHasKey(FixtureEntity::class, $entities);
        self::assertSame(['ignored_columns' => ['secret']], $entities[FixtureEntity::class]);
    }

    public function testEntitiesWithoutAuditIgnoreAreNull(): void
    {
        $config = [
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer($config));

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        $testUser = \ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser::class;
        self::assertArrayHasKey($testUser, $entities);
        self::assertNull(
            $entities[$testUser],
            'Entities without #[AuditIgnore] properties should map to null (no per-entity overrides)',
        );
    }

    private function prependContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 3));
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', $config);

        return $container;
    }
}
