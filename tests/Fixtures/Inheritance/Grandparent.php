<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Fixtures\Inheritance;

use ITKDev\EntityBundle\Attribute\ITKDevEntity;

/**
 * Grandparent in a 3-level inheritance chain used to verify that
 * ITKDevEntityExtension::hasITKDevEntityAttribute() walks past direct parents.
 */
#[ITKDevEntity]
abstract class Grandparent
{
}
