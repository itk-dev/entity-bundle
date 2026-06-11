<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use Doctrine\ORM\EntityManagerInterface;

final readonly class StaleEntityFinder
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param class-string $class
     *
     * @return list<object>
     */
    public function findOlderThan(string $class, \DateTimeImmutable $threshold): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->where('e.createdAt < :threshold')
            ->andWhere('e.anonymizedAt IS NULL')
            ->setParameter('threshold', $threshold);

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
