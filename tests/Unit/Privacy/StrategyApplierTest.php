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

    public function testHashOfNonNullValueIsHex(): void
    {
        $result = $this->applier->apply(Strategy::Hash, 'value');

        self::assertIsString($result);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    public function testHashOfNullReturnsNull(): void
    {
        self::assertNull($this->applier->apply(Strategy::Hash, null));
    }

    public function testHashIsNonDeterministic(): void
    {
        $a = $this->applier->apply(Strategy::Hash, 'alice@example.com');
        $b = $this->applier->apply(Strategy::Hash, 'alice@example.com');

        self::assertNotSame($a, $b);
    }

    public function testHashIsUnlinkableToSource(): void
    {
        // Same source value across two different appliers (different peppers)
        // must still produce unrelated outputs — Hash does not depend on the
        // pepper, so determinism cannot leak via shared deployment secrets.
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
