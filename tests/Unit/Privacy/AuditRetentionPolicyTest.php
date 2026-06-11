<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Privacy;

use ITKDev\EntityBundle\Privacy\AuditRetentionPolicy;
use PHPUnit\Framework\TestCase;

final class AuditRetentionPolicyTest extends TestCase
{
    public function testReturnsDefaultWhenNoOverrideRegistered(): void
    {
        $policy = new AuditRetentionPolicy('P1Y', []);

        $interval = $policy->intervalFor('App\\Some\\Entity');

        self::assertSame('P1Y', self::serialise($interval));
    }

    public function testPerEntityOverrideWinsOverDefault(): void
    {
        $policy = new AuditRetentionPolicy('P1Y', [
            // class-string in the @var sense; the runtime contract is "string class FQCN -> ISO duration",
            // so use existing classes to keep PHPStan happy without invoking Doctrine on them.
            \stdClass::class => 'P30D',
        ]);

        self::assertSame('P30D', self::serialise($policy->intervalFor(\stdClass::class)));
        self::assertSame('P1Y', self::serialise($policy->intervalFor(\ArrayObject::class)));
    }

    /**
     * \DateInterval has no direct ISO serialiser, so re-emit the duration by hand for assertion.
     */
    private static function serialise(\DateInterval $i): string
    {
        $out = 'P';
        if ($i->y > 0) {
            $out .= $i->y.'Y';
        }
        if ($i->m > 0) {
            $out .= $i->m.'M';
        }
        if ($i->d > 0) {
            $out .= $i->d.'D';
        }
        $time = '';
        if ($i->h > 0) {
            $time .= $i->h.'H';
        }
        if ($i->i > 0) {
            $time .= $i->i.'M';
        }
        if ($i->s > 0) {
            $time .= $i->s.'S';
        }
        if ('' !== $time) {
            $out .= 'T'.$time;
        }

        return $out;
    }
}
