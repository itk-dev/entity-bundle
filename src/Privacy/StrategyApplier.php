<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class StrategyApplier
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $pepper,
    ) {
    }

    public function apply(Strategy $strategy, mixed $value, ?string $replacement = null): mixed
    {
        return match ($strategy) {
            Strategy::NullValue => null,
            Strategy::Redact => $replacement ?? '[REDACTED]',
            Strategy::Hash => $value === null ? null : hash('sha256', (string) $value),
            Strategy::Pseudonymize => $value === null
                ? null
                : 'user_'.substr(sha1((string) $value.$this->pepper), 0, 12),
        };
    }
}
