<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use PHPUnit\Framework\TestCase;

final class AnonymizationStatusTraitTest extends TestCase
{
    public function testStartsNotAnonymized(): void
    {
        $entity = new FixtureEntity();

        self::assertFalse($entity->isAnonymized());
        self::assertNull($entity->getAnonymizedAt());
    }

    public function testMarkAnonymized(): void
    {
        $entity = new FixtureEntity();
        $at = new \DateTimeImmutable('2025-06-01');

        $entity->markAnonymized($at);

        self::assertTrue($entity->isAnonymized());
        self::assertSame($at, $entity->getAnonymizedAt());
    }
}
