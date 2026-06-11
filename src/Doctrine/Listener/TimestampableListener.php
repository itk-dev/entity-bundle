<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Listener;

use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Psr\Clock\ClockInterface;

final readonly class TimestampableListener
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof TimestampableInterface) {
                continue;
            }
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof TimestampableInterface) {
                continue;
            }
            $entity->setUpdatedAt($now);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }
}
