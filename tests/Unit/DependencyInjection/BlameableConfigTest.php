<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Doctrine\Listener\BlameableListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BlameableConfigTest extends TestCase
{
    public function testDefaultsToDisabledListenerExcluded(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($this->isExcluded($container), 'BlameableListener must be excluded when blameable is disabled');
    }

    public function testExplicitlyEnabledRegistersListener(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'blameable' => ['enabled' => true],
        ]], $container);

        self::assertFalse($this->isExcluded($container));
        $tags = $container->getDefinition(BlameableListener::class)->getTag('doctrine.event_listener');
        self::assertSame('onFlush', $tags[0]['event'] ?? null);
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        return $container;
    }

    private function isExcluded(ContainerBuilder $container): bool
    {
        if (!$container->hasDefinition(BlameableListener::class)) {
            return true;
        }

        return $container->getDefinition(BlameableListener::class)->hasTag('container.excluded');
    }
}
