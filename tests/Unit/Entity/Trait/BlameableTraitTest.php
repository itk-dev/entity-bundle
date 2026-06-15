<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Entity\Trait;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

final class BlameableTraitTest extends TestCase
{
    public function testStartsNull(): void
    {
        $entity = new FixtureEntity();

        self::assertNull($entity->getCreatedBy());
        self::assertNull($entity->getModifiedBy());
    }

    public function testSettersAcceptUserOrNull(): void
    {
        $entity = new FixtureEntity();
        $user = new TestUser();

        $entity->setCreatedBy($user);
        $entity->setModifiedBy($user);
        self::assertSame($user, $entity->getCreatedBy());
        self::assertSame($user, $entity->getModifiedBy());

        $entity->setCreatedBy(null);
        $entity->setModifiedBy(null);
        self::assertNull($entity->getCreatedBy());
        self::assertNull($entity->getModifiedBy());
    }
}
