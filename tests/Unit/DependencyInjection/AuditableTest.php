<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AuditableTest extends TestCase
{
    public function testOnlyEntitiesWithAuditableAreRegistered(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer());

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        self::assertArrayHasKey(FixtureEntity::class, $entities);
        self::assertArrayHasKey(TestUser::class, $entities);
        self::assertArrayNotHasKey(
            NonAuditableEntity::class,
            $entities,
            'Entities without #[Auditable] must not be registered with dh_auditor',
        );
    }

    public function testNoAuditableEntities_DhAuditorNotPrepended(): void
    {
        $extension = new ITKDevEntityExtension();
        // Point at a path with no #[Auditable] entities.
        $extension->prepend($container = $this->prependContainer([
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
            'entity_paths' => ['%kernel.project_dir%/tests/Unit'],
        ]));

        self::assertSame([], $container->getExtensionConfig('dh_auditor'));
    }

    /** @param array<string, mixed>|null $config */
    private function prependContainer(?array $config = null): ContainerBuilder
    {
        $config ??= [
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ];

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 3));
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', $config);

        return $container;
    }
}
