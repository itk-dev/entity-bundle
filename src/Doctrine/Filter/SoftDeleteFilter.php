<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Filter;

use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }

        $column = $targetEntity->getColumnName('deletedAt');

        return sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }
}
