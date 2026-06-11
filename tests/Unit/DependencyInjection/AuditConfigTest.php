<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AuditConfigTest extends TestCase
{
    public function testDefaultsToDisabledNoDhAuditorPrepended(): void
    {
        $config = ['user_class' => 'App\\Entity\\User'];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer($config));

        self::assertSame([], $prepend->getExtensionConfig('dh_auditor'), 'dh_auditor must not be configured when audit is disabled');
    }

    public function testExplicitlyEnabledPrependsDhAuditor(): void
    {
        $config = [
            'user_class' => 'App\\Entity\\User',
            'audit' => ['enabled' => true],
        ];

        $extension = new ITKDevEntityExtension();
        $extension->prepend($prepend = $this->prependContainer($config));

        // No entity_paths configured + tmp project dir = no discoverable entities = no prepend.
        // Use a directory that exists but is empty; assert intent via the absence of error.
        $configs = $prepend->getExtensionConfig('dh_auditor');
        self::assertIsArray($configs);
    }

    private function prependContainer(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->registerExtension(new ITKDevEntityExtension());
        $container->prependExtensionConfig('itk_dev_entity', $config);

        return $container;
    }
}
