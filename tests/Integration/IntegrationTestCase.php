<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Booting the Symfony kernel in the test env installs error/exception handlers
 * via Symfony\Component\ErrorHandler\ErrorHandler — these survive kernel
 * shutdown, which PHPUnit 13 flags as risky. Restore them in tearDown so the
 * handler stack is balanced.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }
}
