<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

final readonly class AnonymizationReport
{
    public function __construct(
        public int $rowsAnonymized,
        public int $classesAffected,
        public int $auditRowsScrubbed = 0,
    ) {
    }
}
