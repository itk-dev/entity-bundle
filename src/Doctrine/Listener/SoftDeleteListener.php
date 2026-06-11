<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Listener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;
use Psr\Clock\ClockInterface;

final readonly class SoftDeleteListener
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof SoftDeletableInterface) {
                continue;
            }
            if ($entity->isDeleted()) {
                // already soft-deleted — allow the actual DELETE to proceed
                continue;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $entity->setDeletedAt($now);

            $meta = $em->getClassMetadata($entity::class);
            $em->persist($entity);
            $uow->scheduleExtraUpdate($entity, [
                'deletedAt' => [null, $now],
            ]);
            $uow->recomputeSingleEntityChangeSet($meta, $entity);

            self::cancelDeletion($uow, $entity);
        }
    }

    private static function cancelDeletion(UnitOfWork $uow, object $entity): void
    {
        $ref = new \ReflectionProperty(UnitOfWork::class, 'entityDeletions');
        /** @var array<int|string, object> $deletions */
        $deletions = $ref->getValue($uow);
        unset($deletions[spl_object_id($entity)]);
        $ref->setValue($uow, $deletions);
    }
}
