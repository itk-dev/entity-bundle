<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Properties are filled by TimestampableListener on first flush, so they may be
    // uninitialized between `new Entity()` and `$em->flush()`. `?? null` is safe on
    // uninitialized typed properties (PHP 8+) and lets callers null-check before flush.
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt ?? null;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt ?? null;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
