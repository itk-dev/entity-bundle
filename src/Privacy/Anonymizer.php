<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use ITKDev\EntityBundle\Entity\Contract\AnonymizationStatusInterface;

final readonly class Anonymizer
{
    public function __construct(
        private EntityManagerInterface $em,
        private AnonymizationRegistry $registry,
        private RuleApplier $applier,
    ) {
    }

    /**
     * Apply rules + flush. The orchestrator is responsible for scrubbing audit history
     * once the surrounding transaction has committed (audit rows are written by the
     * AuditorBundle middleware on connection commit, not on flush).
     */
    public function anonymize(object $entity): bool
    {
        $rules = $this->registry->rulesFor($entity::class);
        if ([] === $rules) {
            return false;
        }
        if ($entity instanceof AnonymizationStatusInterface && $entity->isAnonymized()) {
            return false;
        }

        $this->applier->apply($entity, $rules);
        $this->em->flush();

        return true;
    }
}
