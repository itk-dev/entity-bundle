<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\UserInterface;
use ITKDev\EntityBundle\Entity\Contract\IdentifiableInterface;

final readonly class SubjectAnonymizer
{
    public function __construct(
        private EntityManagerInterface $em,
        private AnonymizationRegistry $registry,
        private Anonymizer $anonymizer,
        private AuditScrubber $scrubber,
        #[Autowire(param: 'itk_dev_entity.user_class')]
        private string $userClass,
    ) {
    }

    public function anonymize(UserInterface&IdentifiableInterface $subject): AnonymizationReport
    {
        /** @var list<object> $anonymized */
        $anonymized = [];

        $report = $this->em->wrapInTransaction(function () use ($subject, &$anonymized): AnonymizationReport {
            $rows = 0;
            $classes = 0;

            if ($this->anonymizer->anonymize($subject)) {
                $anonymized[] = $subject;
                ++$rows;
                ++$classes;
            }

            foreach ($this->registry->classes() as $class) {
                if ($class === $this->userClass) {
                    continue;
                }

                $userFks = $this->findUserFks($class);
                if ($userFks === []) {
                    continue;
                }

                $rowsInClass = 0;
                foreach ($this->findRowsLinkedToSubject($class, $userFks, $subject) as $row) {
                    if ($this->anonymizer->anonymize($row)) {
                        $anonymized[] = $row;
                        ++$rows;
                        ++$rowsInClass;
                    }
                }
                if ($rowsInClass > 0) {
                    ++$classes;
                }
            }

            return new AnonymizationReport($rows, $classes);
        });

        // Transaction committed — audit rows are now persisted by AuditorConnection::commit.
        // Walk them and scrub any PII in their `diffs` JSON, then NULL out the subject's IP
        // across every audit table where they were the actor.
        foreach ($anonymized as $entity) {
            $this->scrubber->scrubEntityHistory(
                $entity,
                $this->registry->rulesFor($entity::class),
            );
        }
        $this->scrubber->scrubSubjectBlame((string) $subject->getId());

        return $report;
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function findUserFks(string $class): array
    {
        $meta = $this->em->getClassMetadata($class);
        $fks = [];
        foreach ($meta->getAssociationNames() as $field) {
            if ($meta->getAssociationTargetClass($field) !== $this->userClass) {
                continue;
            }
            if (!$meta->isSingleValuedAssociation($field)) {
                continue;
            }
            $fks[] = $field;
        }

        return $fks;
    }

    /**
     * @param class-string $class
     * @param list<string> $userFkProperties
     *
     * @return list<object>
     */
    private function findRowsLinkedToSubject(string $class, array $userFkProperties, UserInterface&IdentifiableInterface $subject): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($class, 'e');

        $orClauses = [];
        foreach ($userFkProperties as $field) {
            $orClauses[] = sprintf('e.%s = :subject', $field);
        }
        $qb->where('('.implode(' OR ', $orClauses).')')
            ->andWhere('e.anonymizedAt IS NULL')
            ->setParameter('subject', $subject->getId(), UlidType::NAME);

        $filters = $this->em->getFilters();
        $wasEnabled = $filters->isEnabled('soft_delete');
        if ($wasEnabled) {
            $filters->disable('soft_delete');
        }

        try {
            return $qb->getQuery()->getResult();
        } finally {
            if ($wasEnabled) {
                $filters->enable('soft_delete');
            }
        }
    }
}
