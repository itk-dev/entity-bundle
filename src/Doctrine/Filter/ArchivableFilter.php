<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Doctrine\Filter;

use ITKDev\EntityBundle\Entity\Contract\ArchivableInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class ArchivableFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->reflClass?->implementsInterface(ArchivableInterface::class)) {
            return '';
        }

        $column = $targetEntity->getColumnName('archivedAt');

        return sprintf('%s.%s IS NULL', $targetTableAlias, $column);
    }
}
