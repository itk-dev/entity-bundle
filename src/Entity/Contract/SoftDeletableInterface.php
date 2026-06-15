<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

interface SoftDeletableInterface
{
    public function getDeletedAt(): ?\DateTimeImmutable;

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void;

    public function isDeleted(): bool;

    public function softDelete(\DateTimeImmutable $at): void;

    public function restore(): void;
}
