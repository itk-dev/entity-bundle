<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Entity;

use ITKDev\EntityBundle\Attribute\ITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\IdentifiableInterface;
use ITKDev\EntityBundle\Entity\Trait\IdentifiableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\MappedSuperclass]
#[ITKDevEntity]
abstract class AbstractITKDevEntity implements IdentifiableInterface
{
    use IdentifiableTrait;

    public function __construct()
    {
        $this->id = new Ulid();
    }
}
