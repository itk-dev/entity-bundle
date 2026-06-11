<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserClassRequirementTest extends TestCase
{
    public function testEmptyConfig_NoUserClass_Loads(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();

        $extension->load([[]], $container);

        self::assertSame('', $container->getParameter('itk_dev_entity.user_class'));
    }

    public function testEmptyConfig_NoResolveTargetEntitiesPrepended(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer([]));

        $doctrine = $prepend->getExtensionConfig('doctrine');
        $orm = $doctrine[0]['orm'] ?? [];
        self::assertArrayNotHasKey(
            'resolve_target_entities',
            $orm,
            'resolve_target_entities must not be prepended when user_class is absent',
        );
    }

    public function testAuditEnabled_WithoutUserClass_Throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('user_class is required when audit or blameable is enabled');

        $extension = new ITKDevEntityExtension();
        $extension->load([['audit' => ['enabled' => true]]], $this->container());
    }

    public function testBlameableEnabled_WithoutUserClass_Throws(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('user_class is required when audit or blameable is enabled');

        $extension = new ITKDevEntityExtension();
        $extension->load([['blameable' => ['enabled' => true]]], $this->container());
    }

    public function testAuditEnabled_WithUserClass_Loads(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
        ]], $container = $this->container());

        self::assertSame('App\\Entity\\User', $container->getParameter('itk_dev_entity.user_class'));
    }

    public function testUserClassSet_PrependsResolveTargetEntities(): void
    {
        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer(['user_class' => 'App\\Entity\\User']));

        $doctrine = $prepend->getExtensionConfig('doctrine');
        $orm = $doctrine[0]['orm'] ?? [];
        self::assertSame(
            ['App\\Entity\\User'],
            array_values($orm['resolve_target_entities'] ?? []),
        );
        self::assertArrayHasKey(UserInterface::class, $orm['resolve_target_entities']);
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
