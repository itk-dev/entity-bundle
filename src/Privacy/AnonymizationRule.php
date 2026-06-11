<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

final readonly class AnonymizationRule
{
    public function __construct(
        public string $property,
        public Strategy $strategy,
        public ?string $replacement = null,
    ) {
    }
}
