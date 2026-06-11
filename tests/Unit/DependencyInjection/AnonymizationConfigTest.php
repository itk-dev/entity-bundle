<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\Command\PrivacyAnonymizeCommand;
use ITKDev\EntityBundle\Command\PrivacyAnonymizeStaleCommand;
use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Privacy\AnonymizationRegistry;
use ITKDev\EntityBundle\Privacy\Anonymizer;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AnonymizationConfigTest extends TestCase
{
    public function testDefaultsToDisabledNoPrivacyServicesOrCommands(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([[]], $container);

        self::assertFalse($container->hasDefinition(Anonymizer::class));
        self::assertFalse($container->hasDefinition(SubjectAnonymizer::class));
        self::assertFalse($container->hasDefinition(AnonymizationRegistry::class));
        self::assertFalse($container->hasDefinition(PrivacyAnonymizeCommand::class));
        self::assertFalse($container->hasDefinition(PrivacyAnonymizeStaleCommand::class));

        self::assertSame([], $container->getParameter('itk_dev_entity.anonymization_rules'));
    }

    public function testExplicitlyEnabledRegistersPrivacyServicesAndCommands(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'anonymization' => ['enabled' => true],
        ]], $container);

        self::assertTrue($container->hasDefinition(Anonymizer::class));
        self::assertTrue($container->hasDefinition(SubjectAnonymizer::class));
        self::assertTrue($container->hasDefinition(AnonymizationRegistry::class));
        self::assertTrue($container->hasDefinition(PrivacyAnonymizeCommand::class));
        self::assertTrue($container->hasDefinition(PrivacyAnonymizeStaleCommand::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        return $container;
    }
}
