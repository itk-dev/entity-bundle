<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Psr\Clock\ClockInterface;

final readonly class BulkAnonymizer
{
    public function __construct(
        private AnonymizationRegistry $registry,
        private StaleEntityFinder $finder,
        private Anonymizer $anonymizer,
        private AuditScrubber $scrubber,
        private AuditRetentionPolicy $retention,
        private ClockInterface $clock,
    ) {
    }

    public function anonymizeOlderThan(\DateInterval $interval, bool $dryRun = false): AnonymizationReport
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $threshold = $now->sub($interval);

        $totalRows = 0;
        $classesAffected = 0;
        $auditRowsScrubbed = 0;

        foreach ($this->registry->classes() as $class) {
            $rows = $this->finder->findOlderThan($class, $threshold);
            $rules = $this->registry->rulesFor($class);

            $changedInClass = 0;
            foreach ($rows as $row) {
                if ($dryRun) {
                    ++$changedInClass;
                    continue;
                }
                if ($this->anonymizer->anonymize($row)) {
                    $this->scrubber->scrubEntityHistory($row, $rules);
                    ++$changedInClass;
                }
            }

            if ($changedInClass > 0) {
                $totalRows += $changedInClass;
                ++$classesAffected;
            }

            $auditRowsScrubbed += $this->retentionSweep($class, $now, $dryRun);
        }

        return new AnonymizationReport($totalRows, $classesAffected, $auditRowsScrubbed);
    }

    /**
     * @param class-string $class
     */
    private function retentionSweep(string $class, \DateTimeImmutable $now, bool $dryRun): int
    {
        $auditThreshold = $now->sub($this->retention->intervalFor($class));

        if ($dryRun) {
            return $this->scrubber->countAuditOlderThan($class, $auditThreshold);
        }

        return $this->scrubber->scrubAuditOlderThan($class, $auditThreshold);
    }
}
