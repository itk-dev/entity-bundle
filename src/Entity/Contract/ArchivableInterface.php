<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

interface ArchivableInterface
{
    public function getArchivedAt(): ?\DateTimeImmutable;

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): void;

    public function isArchived(): bool;

    public function archive(\DateTimeImmutable $at): void;

    public function unarchive(): void;
}
