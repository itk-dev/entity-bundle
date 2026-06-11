<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Listener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use ITKDev\EntityBundle\Entity\Contract\BlameableInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class BlameableListener
{
    public function __construct(private Security $security)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $current = $this->security->getUser();
        $user = $current instanceof UserInterface ? $current : null;

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof BlameableInterface) {
                continue;
            }
            $changed = false;
            if (null === $entity->getCreatedBy() && null !== $user) {
                $entity->setCreatedBy($user);
                $changed = true;
            }
            if (null === $entity->getModifiedBy() && null !== $user) {
                $entity->setModifiedBy($user);
                $changed = true;
            }
            if ($changed) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof BlameableInterface) {
                continue;
            }
            $entity->setModifiedBy($user);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }
}
