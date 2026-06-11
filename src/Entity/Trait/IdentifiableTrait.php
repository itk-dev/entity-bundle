<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

trait IdentifiableTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    public function getId(): Ulid
    {
        return $this->id;
    }
}
