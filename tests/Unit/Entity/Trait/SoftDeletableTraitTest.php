<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;

final class SoftDeletableTraitTest extends TestCase
{
    public function testStartsNotDeleted(): void
    {
        $entity = new FixtureEntity();

        self::assertFalse($entity->isDeleted());
        self::assertNull($entity->getDeletedAt());
    }

    public function testSoftDeleteThenRestore(): void
    {
        $entity = new FixtureEntity();
        $at = new \DateTimeImmutable('2025-06-01');

        $entity->softDelete($at);
        self::assertTrue($entity->isDeleted());
        self::assertSame($at, $entity->getDeletedAt());

        $entity->restore();
        self::assertFalse($entity->isDeleted());
        self::assertNull($entity->getDeletedAt());
    }

    public function testSetDeletedAtDirectly(): void
    {
        $entity = new FixtureEntity();
        $at = new \DateTimeImmutable('2025-06-01');

        $entity->setDeletedAt($at);
        self::assertSame($at, $entity->getDeletedAt());

        $entity->setDeletedAt(null);
        self::assertNull($entity->getDeletedAt());
    }
}
