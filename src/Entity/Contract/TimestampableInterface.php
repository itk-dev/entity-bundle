<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): void;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void;
}
