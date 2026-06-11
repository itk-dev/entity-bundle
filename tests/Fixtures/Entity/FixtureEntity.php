<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Entity;

use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\AnonymizationStatusInterface;
use ITKDev\EntityBundle\Entity\Contract\ArchivableInterface;
use ITKDev\EntityBundle\Entity\Contract\BlameableInterface;
use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\AnonymizationStatusTrait;
use ITKDev\EntityBundle\Entity\Trait\ArchivableTrait;
use ITKDev\EntityBundle\Entity\Trait\BlameableTrait;
use ITKDev\EntityBundle\Entity\Trait\SoftDeletableTrait;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;
use ITKDev\EntityBundle\Audit\Attribute\AuditIgnore;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use ITKDev\EntityBundle\Privacy\Attribute\Anonymize;
use ITKDev\EntityBundle\Privacy\Strategy;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_fixture_entity')]
#[Auditable]
class FixtureEntity extends AbstractITKDevEntity implements
    TimestampableInterface,
    BlameableInterface,
    SoftDeletableInterface,
    ArchivableInterface,
    AnonymizationStatusInterface
{
    use TimestampableTrait;
    use BlameableTrait;
    use SoftDeletableTrait;
    use ArchivableTrait;
    use AnonymizationStatusTrait;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Anonymize(strategy: Strategy::Redact)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[AuditIgnore]
    private ?string $secret = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }
}
