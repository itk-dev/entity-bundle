<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\AttributeOnlyEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ITKDevEntityDiscoveryTest extends TestCase
{
    public function testAttributeOnlyEntityDiscoveredWithoutAbstractITKDevEntity(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer());

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        self::assertArrayHasKey(
            AttributeOnlyEntity::class,
            $entities,
            'Classes marked with #[ITKDevEntity] should be discovered even when they do not extend AbstractITKDevEntity',
        );
    }

    public function testAbstractITKDevEntitySubclassesStillDiscovered(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer());

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        // FixtureEntity extends AbstractITKDevEntity which carries #[ITKDevEntity];
        // the parent-chain walk in discovery picks it up.
        self::assertArrayHasKey(FixtureEntity::class, $entities);
    }

    public function testUnmarkedEntitiesNotInAuditorConfig(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($container = $this->prependContainer());

        $auditor = $container->getExtensionConfig('dh_auditor');
        $entities = $auditor[0]['providers']['doctrine']['entities'] ?? [];

        // NonAuditableEntity extends AbstractITKDevEntity (so it's discovered) but lacks #[Auditable].
        self::assertArrayNotHasKey(NonAuditableEntity::class, $entities);
    }

    private function prependContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 3));
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', [
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
            'entity_paths' => ['%kernel.project_dir%/tests/Fixtures/Entity'],
        ]);

        return $container;
    }
}
