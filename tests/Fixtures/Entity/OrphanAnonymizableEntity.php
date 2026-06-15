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

/**
 * Anonymizable entity with no foreign key to TestUser, exercising SubjectAnonymizer's
 * "skip class that has no user FK" branch.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_orphan_anonymizable')]
#[Auditable]
class OrphanAnonymizableEntity extends AbstractITKDevEntity implements TimestampableInterface, AnonymizationStatusInterface
{
    use TimestampableTrait;
    use AnonymizationStatusTrait;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Anonymize(strategy: Strategy::Redact)]
    private ?string $payload = null;

    public function setPayload(?string $payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }
}
