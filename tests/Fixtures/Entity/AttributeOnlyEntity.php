<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Attribute\ITKDevEntity;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use Symfony\Component\Uid\Ulid;

/**
 * Demonstrates that #[ITKDevEntity] alone makes the bundle discover an entity —
 * no inheritance from AbstractITKDevEntity required.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_attribute_only')]
#[ITKDevEntity]
#[Auditable]
class AttributeOnlyEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }
}
