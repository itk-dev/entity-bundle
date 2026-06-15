<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Contract;

use Symfony\Component\Uid\Ulid;

interface IdentifiableInterface
{
    public function getId(): Ulid;
}
