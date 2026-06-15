<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;

final class TimestampableTraitTest extends TestCase
{
    public function testStartsNull(): void
    {
        $entity = new FixtureEntity();

        self::assertNull($entity->getCreatedAt());
        self::assertNull($entity->getUpdatedAt());
    }

    public function testSettersStoreValue(): void
    {
        $entity = new FixtureEntity();
        $when = new \DateTimeImmutable('2025-01-02 03:04:05');

        $entity->setCreatedAt($when);
        $entity->setUpdatedAt($when);

        self::assertSame($when, $entity->getCreatedAt());
        self::assertSame($when, $entity->getUpdatedAt());
    }
}
