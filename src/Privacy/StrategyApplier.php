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
            // Per-call random output — unlinkable to the source value and to any other
            // row anonymized with the same strategy. Pepper is intentionally unused here
            // so the result cannot be recomputed from the cleartext even by an operator
            // who knows kernel.secret.
            Strategy::Hash => null === $value ? null : bin2hex(random_bytes(32)),
            Strategy::Pseudonymize => null === $value
                ? null
                : 'user_'.substr(hash('sha256', (string) $value.$this->pepper), 0, 12),
        };
    }
}
