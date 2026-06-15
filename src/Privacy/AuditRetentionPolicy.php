<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AuditRetentionPolicy
{
    private \DateInterval $default;

    /** @var array<class-string, \DateInterval> */
    private array $overrides;

    /**
     * @param array<class-string, string> $rawOverrides
     */
    public function __construct(
        #[Autowire(param: 'itk_dev_entity.audit_retention')]
        string $defaultIso,
        #[Autowire(param: 'itk_dev_entity.audit_retention_overrides')]
        array $rawOverrides,
    ) {
        $this->default = new \DateInterval($defaultIso);
        $overrides = [];
        foreach ($rawOverrides as $class => $iso) {
            $overrides[$class] = new \DateInterval($iso);
        }
        $this->overrides = $overrides;
    }

    public function intervalFor(string $class): \DateInterval
    {
        return $this->overrides[$class] ?? $this->default;
    }
}
