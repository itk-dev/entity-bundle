<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy\Attribute;

use ITKDev\EntityBundle\Privacy\Strategy;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Anonymize
{
    public function __construct(
        public Strategy $strategy,
        public ?string $replacement = null,
    ) {
    }
}
