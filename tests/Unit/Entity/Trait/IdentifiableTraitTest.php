<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class IdentifiableTraitTest extends TestCase
{
    public function testIdIsGeneratedOnConstruct(): void
    {
        $entity = new FixtureEntity();

        self::assertInstanceOf(Ulid::class, $entity->getId());
    }

    public function testEachInstanceHasUniqueId(): void
    {
        $a = new FixtureEntity();
        $b = new FixtureEntity();

        self::assertNotSame((string) $a->getId(), (string) $b->getId());
    }
}
