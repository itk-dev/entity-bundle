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
            // remove() left the entity in STATE_REMOVED; persist() brings it back to
            // STATE_MANAGED so scheduleExtraUpdate() will accept it.
            $em->persist($entity);
            $uow->scheduleExtraUpdate($entity, [
                'deletedAt' => [null, $now],
            ]);
            $uow->recomputeSingleEntityChangeSet($meta, $entity);

            self::cancelDeletion($uow, $entity);
        }
    }

    /**
     * Doctrine ORM 3 has no public API to cancel a scheduled deletion, so we reach into
     * the UnitOfWork's private $entityDeletions map and remove the entry directly. The
     * composer.json constraint pins doctrine/orm to ^3 — if that constraint is widened,
     * verify this property still exists (see SoftDeleteListenerInternalsTest).
     */
    private static function cancelDeletion(UnitOfWork $uow, object $entity): void
    {
        $ref = new \ReflectionProperty(UnitOfWork::class, 'entityDeletions');
        /** @var array<int|string, object> $deletions */
        $deletions = $ref->getValue($uow);
        unset($deletions[spl_object_id($entity)]);
        $ref->setValue($uow, $deletions);
    }
}
