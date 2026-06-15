<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

interface AnonymizationStatusInterface
{
    public function getAnonymizedAt(): ?\DateTimeImmutable;

    public function isAnonymized(): bool;

    public function markAnonymized(\DateTimeImmutable $at): void;
}
