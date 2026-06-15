<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait AnonymizationStatusTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function isAnonymized(): bool
    {
        return null !== $this->anonymizedAt;
    }

    public function markAnonymized(\DateTimeImmutable $at): void
    {
        $this->anonymizedAt = $at;
    }
}
