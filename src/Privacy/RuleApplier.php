<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use ITKDev\EntityBundle\Entity\Contract\AnonymizationStatusInterface;
use Psr\Clock\ClockInterface;

final readonly class RuleApplier
{
    public function __construct(
        private StrategyApplier $strategyApplier,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<AnonymizationRule> $rules
     */
    public function apply(object $entity, array $rules): void
    {
        $ref = new \ReflectionObject($entity);
        foreach ($rules as $rule) {
            $prop = $this->locateProperty($ref, $rule->property);
            $current = $prop->getValue($entity);
            $new = $this->strategyApplier->apply($rule->strategy, $current, $rule->replacement);
            $prop->setValue($entity, $new);
        }

        if ($entity instanceof AnonymizationStatusInterface && !$entity->isAnonymized()) {
            $entity->markAnonymized(\DateTimeImmutable::createFromInterface($this->clock->now()));
        }
    }

    /**
     * @param \ReflectionClass<object> $ref
     */
    private function locateProperty(\ReflectionClass $ref, string $name): \ReflectionProperty
    {
        $cursor = $ref;
        while ($cursor instanceof \ReflectionClass) {
            if ($cursor->hasProperty($name)) {
                return $cursor->getProperty($name);
            }
            $cursor = $cursor->getParentClass() ?: null;
        }
        throw new \InvalidArgumentException(sprintf('Property "%s" not found on %s', $name, $ref->getName()));
    }
}
