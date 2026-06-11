<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Trait;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;

trait BlameableTrait
{
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserInterface $createdBy = null;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserInterface $modifiedBy = null;

    public function getCreatedBy(): ?UserInterface
    {
        return $this->createdBy;
    }

    public function getModifiedBy(): ?UserInterface
    {
        return $this->modifiedBy;
    }

    public function setCreatedBy(?UserInterface $user): void
    {
        $this->createdBy = $user;
    }

    public function setModifiedBy(?UserInterface $user): void
    {
        $this->modifiedBy = $user;
    }
}
