<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Privacy;

use ITKDev\EntityBundle\Privacy\Strategy;
use ITKDev\EntityBundle\Privacy\StrategyApplier;
use PHPUnit\Framework\TestCase;

final class StrategyApplierTest extends TestCase
{
    private StrategyApplier $applier;

    protected function setUp(): void
    {
        $this->applier = new StrategyApplier('test-pepper');
    }

    public function testNullValueAlwaysReturnsNull(): void
    {
        self::assertNull($this->applier->apply(Strategy::NullValue, 'sensitive'));
        self::assertNull($this->applier->apply(Strategy::NullValue, null));
    }

    public function testRedactWithDefault(): void
    {
        self::assertSame('[REDACTED]', $this->applier->apply(Strategy::Redact, 'alice@example.com'));
    }

    public function testRedactWithReplacement(): void
    {
        self::assertSame('[GONE]', $this->applier->apply(Strategy::Redact, 'value', '[GONE]'));
    }

    public function testHashOfNonNullValue(): void
    {
        self::assertSame(hash('sha256', 'value'.'test-pepper'), $this->applier->apply(Strategy::Hash, 'value'));
    }

    public function testHashOfNullReturnsNull(): void
    {
        self::assertNull($this->applier->apply(Strategy::Hash, null));
    }

    public function testHashIsPepperDependent(): void
    {
        $other = new StrategyApplier('different-pepper');

        self::assertNotSame(
            $this->applier->apply(Strategy::Hash, 'alice@example.com'),
            $other->apply(Strategy::Hash, 'alice@example.com'),
        );
    }

    public function testPseudonymizeIsDeterministic(): void
    {
        $a = $this->applier->apply(Strategy::Pseudonymize, 'alice@example.com');
        $b = $this->applier->apply(Strategy::Pseudonymize, 'alice@example.com');

        self::assertSame($a, $b);
        self::assertStringStartsWith('user_', $a);
    }

    public function testPseudonymizeDiffersAcrossInputs(): void
    {
        $a = $this->applier->apply(Strategy::Pseudonymize, 'alice@example.com');
        $b = $this->applier->apply(Strategy::Pseudonymize, 'bob@example.com');

        self::assertNotSame($a, $b);
    }

    public function testPseudonymizeOfNullReturnsNull(): void
    {
        self::assertNull($this->applier->apply(Strategy::Pseudonymize, null));
    }

    public function testPseudonymizeIsPepperDependent(): void
    {
        $other = new StrategyApplier('different-pepper');

        self::assertNotSame(
            $this->applier->apply(Strategy::Pseudonymize, 'alice@example.com'),
            $other->apply(Strategy::Pseudonymize, 'alice@example.com'),
        );
    }
}
