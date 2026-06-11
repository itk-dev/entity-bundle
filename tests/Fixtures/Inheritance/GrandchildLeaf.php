<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Inheritance;

use ITKDev\EntityBundle\Audit\Attribute\Auditable;

/**
 * Concrete leaf two levels below the class carrying #[ITKDevEntity]. The bundle's
 * parent-chain walk must reach Grandparent for this class to be discovered.
 */
#[Auditable]
final class GrandchildLeaf extends IntermediateParent
{
}
