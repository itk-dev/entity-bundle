<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

use Symfony\Component\Security\Core\User\UserInterface;

interface BlameableInterface
{
    public function getCreatedBy(): ?UserInterface;

    public function getModifiedBy(): ?UserInterface;

    public function setCreatedBy(?UserInterface $user): void;

    public function setModifiedBy(?UserInterface $user): void;
}
