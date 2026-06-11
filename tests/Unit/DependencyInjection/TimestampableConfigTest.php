<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\DependencyInjection;

use ITKDev\EntityBundle\DependencyInjection\ITKDevEntityExtension;
use ITKDev\EntityBundle\Doctrine\Listener\TimestampableListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TimestampableConfigTest extends TestCase
{
    public function testDefaultsToDisabled_ListenerExcluded(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($this->isExcluded($container), 'TimestampableListener must be excluded when timestampable is disabled');
    }

    public function testExplicitlyEnabled_RegistersListener(): void
    {
        $container = $this->container();
        $extension = new ITKDevEntityExtension();
        $extension->load([[
            'user_class' => 'App\\Entity\\User',
            'timestampable' => ['enabled' => true],
        ]], $container);

        self::assertFalse($this->isExcluded($container));
        $tags = $container->getDefinition(TimestampableListener::class)->getTag('doctrine.event_listener');
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
        if (!$container->hasDefinition(TimestampableListener::class)) {
            return true;
        }

        return $container->getDefinition(TimestampableListener::class)->hasTag('container.excluded');
    }
}
