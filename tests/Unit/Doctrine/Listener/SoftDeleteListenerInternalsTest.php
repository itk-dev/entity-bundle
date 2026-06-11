<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Doctrine\Listener;

use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * Guards the private-property access in SoftDeleteListener::cancelDeletion(): if Doctrine
 * ever renames or removes UnitOfWork::$entityDeletions, soft-delete would silently degrade
 * to a hard delete. This test fails loudly instead.
 */
final class SoftDeleteListenerInternalsTest extends TestCase
{
    public function testUnitOfWorkStillHasEntityDeletionsProperty(): void
    {
        self::assertTrue(
            (new \ReflectionClass(UnitOfWork::class))->hasProperty('entityDeletions'),
            'Doctrine UnitOfWork no longer exposes $entityDeletions — SoftDeleteListener::cancelDeletion() needs updating.',
        );
    }
}
