<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;

final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(SoftDeletableInterface::class)) {
            return '';
        }

        $column = $this->getConnection()->quoteIdentifier($targetEntity->getColumnName('deletedAt'));

        return sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }
}
