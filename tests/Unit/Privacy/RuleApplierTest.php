<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Privacy;

use ITKDev\EntityBundle\Privacy\AnonymizationRule;
use ITKDev\EntityBundle\Privacy\RuleApplier;
use ITKDev\EntityBundle\Privacy\Strategy;
use ITKDev\EntityBundle\Privacy\StrategyApplier;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class RuleApplierTest extends TestCase
{
    public function testLocatesPropertyDeclaredPrivatelyOnParentClass(): void
    {
        $applier = $this->newApplier();
        $entity = new RuleApplierTestChild();

        $applier->apply($entity, [
            new AnonymizationRule('parentSecret', Strategy::Redact, '[GONE]'),
        ]);

        self::assertSame('[GONE]', $entity->getParentSecret());
    }

    public function testThrowsWhenRulePropertyDoesNotExist(): void
    {
        $applier = $this->newApplier();
        $entity = new RuleApplierTestChild();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property "missingField" not found on');

        $applier->apply($entity, [
            new AnonymizationRule('missingField', Strategy::Redact, null),
        ]);
    }

    private function newApplier(): RuleApplier
    {
        $clock = new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2025-01-01');
            }
        };

        return new RuleApplier(new StrategyApplier('test-pepper'), $clock);
    }
}

class RuleApplierTestParent
{
    private string $parentSecret = 'original';

    public function getParentSecret(): string
    {
        return $this->parentSecret;
    }
}

class RuleApplierTestChild extends RuleApplierTestParent
{
}
