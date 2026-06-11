<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;

#[ORM\Entity]
#[ORM\Table(name: 'test_user')]
#[Auditable]
class TestUser extends AbstractITKDevEntity implements UserInterface
{
    public function getUserIdentifier(): string
    {
        return (string) $this->getId();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
