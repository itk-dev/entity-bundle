<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Unit\Privacy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * Covers the per-association filter branches of SubjectAnonymizer::findUserFks:
 *  - skip associations whose target class is not the configured user class
 *  - skip associations that are multi-valued (many-to-many / one-to-many)
 *
 * Both branches are unreachable from the existing integration fixtures (FixtureEntity
 * has only single-valued associations targeting TestUser), so this is unit-style.
 */
final class SubjectAnonymizerFindUserFksTest extends TestCase
{
    public function testFindUserFksReturnsOnlySingleValuedAssociationsTargetingUserClass(): void
    {
        $meta = $this->createStub(ClassMetadata::class);
        $meta->method('getAssociationNames')->willReturn(['createdBy', 'parent', 'collaborators']);
        $meta->method('getAssociationTargetClass')->willReturnMap([
            ['createdBy', TestUser::class],
            ['parent', \stdClass::class],            // not the user class → line 91 continue
            ['collaborators', TestUser::class],
        ]);
        $meta->method('isSingleValuedAssociation')->willReturnMap([
            ['createdBy', true],
            ['collaborators', false],                // multi-valued → line 94 continue
        ]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($meta);

        $anonymizer = (new \ReflectionClass(SubjectAnonymizer::class))->newInstanceWithoutConstructor();
        $this->setPrivate($anonymizer, 'em', $em);
        $this->setPrivate($anonymizer, 'userClass', TestUser::class);

        $method = new \ReflectionMethod(SubjectAnonymizer::class, 'findUserFks');
        $fks = $method->invoke($anonymizer, \stdClass::class);

        self::assertSame(['createdBy'], $fks);
    }

    private function setPrivate(object $target, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($target::class, $property);
        $ref->setValue($target, $value);
    }
}
