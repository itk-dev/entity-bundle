<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\AnonymizationStatusInterface;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\AnonymizationStatusTrait;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;
use ITKDev\EntityBundle\Privacy\Attribute\Anonymize;
use ITKDev\EntityBundle\Privacy\Strategy;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'test_user')]
#[Auditable]
class TestUser extends AbstractITKDevEntity implements UserInterface, TimestampableInterface, AnonymizationStatusInterface
{
    use TimestampableTrait;
    use AnonymizationStatusTrait;

    #[ORM\Column(type: 'string', length: 191, nullable: true)]
    #[Anonymize(strategy: Strategy::Redact)]
    private ?string $email = null;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
}
