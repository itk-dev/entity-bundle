<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Entity;

use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_non_auditable_entity')]
class NonAuditableEntity extends AbstractITKDevEntity
{
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $label = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }
}
