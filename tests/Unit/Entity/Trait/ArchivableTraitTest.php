<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;

final class ArchivableTraitTest extends TestCase
{
    public function testStartsNotArchived(): void
    {
        $entity = new FixtureEntity();

        self::assertFalse($entity->isArchived());
        self::assertNull($entity->getArchivedAt());
    }

    public function testArchiveThenUnarchive(): void
    {
        $entity = new FixtureEntity();
        $at = new \DateTimeImmutable('2025-06-01');

        $entity->archive($at);
        self::assertTrue($entity->isArchived());
        self::assertSame($at, $entity->getArchivedAt());

        $entity->unarchive();
        self::assertFalse($entity->isArchived());
        self::assertNull($entity->getArchivedAt());
    }

    public function testSetArchivedAtDirectly(): void
    {
        $entity = new FixtureEntity();
        $at = new \DateTimeImmutable('2025-06-01');

        $entity->setArchivedAt($at);
        self::assertSame($at, $entity->getArchivedAt());

        $entity->setArchivedAt(null);
        self::assertNull($entity->getArchivedAt());
    }
}
