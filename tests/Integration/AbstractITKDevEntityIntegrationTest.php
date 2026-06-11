<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Entity\Contract\IdentifiableInterface;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\AttributeOnlyEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

final class AbstractITKDevEntityIntegrationTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }

    public function testTimestampsAreSetOnInsertAndUpdate(): void
    {
        $clock = self::getContainer()->get('clock');
        $clock->modify('2025-01-01 10:00:00');

        $entity = new FixtureEntity();
        $entity->setLabel('first');
        $this->em->persist($entity);
        $this->em->flush();

        $created = $entity->getCreatedAt();
        $updated = $entity->getUpdatedAt();
        self::assertNotNull($created);
        self::assertNotNull($updated);
        self::assertSame($created->getTimestamp(), $updated->getTimestamp());

        $clock->modify('2025-01-02 11:00:00');
        $entity->setLabel('second');
        $this->em->flush();

        self::assertSame($created->getTimestamp(), $entity->getCreatedAt()?->getTimestamp());
        self::assertGreaterThan($created->getTimestamp(), $entity->getUpdatedAt()?->getTimestamp());
    }

    public function testBlameableSetsCurrentUserOnInsertAndUpdate(): void
    {
        $alice = new TestUser();
        $bob = new TestUser();
        $this->em->persist($alice);
        $this->em->persist($bob);
        $this->em->flush();

        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        self::assertSame($alice->getId(), $this->blameId($entity->getCreatedBy()));
        self::assertSame($alice->getId(), $this->blameId($entity->getModifiedBy()));

        $this->loginAs($bob);
        $entity->setLabel('mutated');
        $this->em->flush();

        self::assertSame($alice->getId(), $this->blameId($entity->getCreatedBy()));
        self::assertSame($bob->getId(), $this->blameId($entity->getModifiedBy()));
    }

    public function testBlameableTolelratesNullSecurityContext(): void
    {
        $this->logout();

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        self::assertNull($entity->getCreatedBy());
        self::assertNull($entity->getModifiedBy());
    }

    public function testBlameablePreservesModifiedByWhenUpdateRunsLoggedOut(): void
    {
        $alice = new TestUser();
        $this->em->persist($alice);
        $this->em->flush();

        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();
        self::assertSame($alice->getId(), $this->blameId($entity->getModifiedBy()));

        // Mimic a worker / cron flush: no security token.
        $this->logout();
        $entity->setLabel('mutated-by-worker');
        $this->em->flush();

        self::assertSame(
            $alice->getId(),
            $this->blameId($entity->getModifiedBy()),
            'modifiedBy must not be wiped when an update flushes without an authenticated user',
        );
    }

    public function testSoftDeleteAndArchivableFiltersCompose(): void
    {
        $live = new FixtureEntity();
        $live->setLabel('live');

        $archivedOnly = new FixtureEntity();
        $archivedOnly->setLabel('archived');
        $archivedOnly->archive(new \DateTimeImmutable('2025-01-01'));

        $softDeletedOnly = new FixtureEntity();
        $softDeletedOnly->setLabel('soft-deleted');

        $both = new FixtureEntity();
        $both->setLabel('both');
        $both->archive(new \DateTimeImmutable('2025-01-01'));

        $this->em->persist($live);
        $this->em->persist($archivedOnly);
        $this->em->persist($softDeletedOnly);
        $this->em->persist($both);
        $this->em->flush();

        $this->em->remove($softDeletedOnly);
        $this->em->remove($both);
        $this->em->flush();
        $this->em->clear();

        // soft_delete on (default), archivable off (default) -> live + archivedOnly visible.
        $visible = $this->labelsOf($this->em->getRepository(FixtureEntity::class)->findAll());
        sort($visible);
        self::assertSame(['archived', 'live'], $visible);

        // Both filters on -> only `live`.
        $this->em->getFilters()->enable('archivable');
        $this->em->clear();
        $visible = $this->labelsOf($this->em->getRepository(FixtureEntity::class)->findAll());
        self::assertSame(['live'], $visible);

        // soft_delete off, archivable on -> live + soft-deleted.
        $this->em->getFilters()->disable('soft_delete');
        $this->em->clear();
        $visible = $this->labelsOf($this->em->getRepository(FixtureEntity::class)->findAll());
        sort($visible);
        self::assertSame(['live', 'soft-deleted'], $visible);

        // Both off -> all four.
        $this->em->getFilters()->disable('archivable');
        $this->em->clear();
        self::assertCount(4, $this->em->getRepository(FixtureEntity::class)->findAll());
    }

    public function testRemoveTriggersSoftDeleteAndFilterHidesRow(): void
    {
        $entity = new FixtureEntity();
        $entity->setLabel('victim');
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->getId();

        $this->em->remove($entity);
        $this->em->flush();
        $this->em->clear();

        // With soft_delete filter on (default): row hidden
        $found = $this->em->getRepository(FixtureEntity::class)->find($id);
        self::assertNull($found);

        // Disable filter: row visible with deletedAt set
        $this->em->getFilters()->disable('soft_delete');
        $found = $this->em->getRepository(FixtureEntity::class)->find($id);
        self::assertNotNull($found);
        self::assertTrue($found->isDeleted());
        self::assertSame('victim', $found->getLabel());
    }

    public function testSecondRemoveAfterSoftDeleteHardDeletes(): void
    {
        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();
        $id = $entity->getId();

        $this->em->remove($entity);
        $this->em->flush();
        $this->em->clear();

        $this->em->getFilters()->disable('soft_delete');
        $entity = $this->em->getRepository(FixtureEntity::class)->find($id);
        self::assertNotNull($entity);

        $this->em->remove($entity);
        $this->em->flush();
        $this->em->clear();

        $found = $this->em->getRepository(FixtureEntity::class)->find($id);
        self::assertNull($found, 'second remove() should hard-delete an already soft-deleted row');
    }

    public function testArchivableFilterHidesArchivedRowsWhenEnabled(): void
    {
        $live = new FixtureEntity();
        $live->setLabel('live');
        $archived = new FixtureEntity();
        $archived->setLabel('archived');
        $archived->archive(new \DateTimeImmutable('2025-01-01'));

        $this->em->persist($live);
        $this->em->persist($archived);
        $this->em->flush();
        $this->em->clear();

        // Filter disabled by default — both visible
        $all = $this->em->getRepository(FixtureEntity::class)->findAll();
        self::assertCount(2, $all);

        // Enable filter — only live row visible
        $this->em->getFilters()->enable('archivable');
        $this->em->clear();
        $visible = $this->em->getRepository(FixtureEntity::class)->findAll();
        self::assertCount(1, $visible);
        self::assertSame('live', $visible[0]->getLabel());
    }

    public function testArchivableFilterReturnsNoConstraintForNonArchivableEntity(): void
    {
        $entity = new AttributeOnlyEntity();
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();

        $this->em->getFilters()->enable('archivable');

        // AttributeOnlyEntity does not implement ArchivableInterface, so the filter must
        // short-circuit with an empty constraint — otherwise the query would emit SQL
        // referencing a non-existent archived_at column on test_attribute_only.
        $rows = $this->em->getRepository(AttributeOnlyEntity::class)->findAll();
        self::assertCount(1, $rows);
    }

    public function testSoftDeleteFilterReturnsNoConstraintForNonSoftDeletableEntity(): void
    {
        $entity = new AttributeOnlyEntity();
        $this->em->persist($entity);
        $this->em->flush();
        $this->em->clear();

        // soft_delete is enabled by default. AttributeOnlyEntity does not implement
        // SoftDeletableInterface, so the filter must short-circuit rather than reference
        // a non-existent deleted_at column.
        $rows = $this->em->getRepository(AttributeOnlyEntity::class)->findAll();
        self::assertCount(1, $rows);
    }

    public function testListenersSkipEntitiesWithoutTheirRespectiveInterface(): void
    {
        // NonAuditableEntity implements none of Timestampable/Blameable/SoftDeletable, so
        // every per-feature onFlush listener must `continue` past it during insert, update
        // and delete flushes. Exercises the skip-branches of all three listeners and the
        // soft-delete filter's empty-constraint path for the same entity.
        $alice = new TestUser();
        $this->em->persist($alice);
        $this->em->flush();
        $this->loginAs($alice);

        $entity = new NonAuditableEntity();
        $entity->setLabel('initial');
        $this->em->persist($entity);
        $this->em->flush();

        $entity->setLabel('changed');
        $this->em->flush();

        $this->em->remove($entity);
        $this->em->flush();
        $this->em->clear();

        self::assertCount(0, $this->em->getRepository(NonAuditableEntity::class)->findAll());
    }

    /**
     * @param list<FixtureEntity> $entities
     *
     * @return list<string>
     */
    private function labelsOf(array $entities): array
    {
        return array_values(array_filter(array_map(static fn (FixtureEntity $e): ?string => $e->getLabel(), $entities)));
    }

    private function blameId(?UserInterface $user): ?Ulid
    {
        if (null === $user) {
            return null;
        }
        self::assertInstanceOf(IdentifiableInterface::class, $user);

        return $user->getId();
    }

    private function loginAs(TestUser $user): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
    }

    private function logout(): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(null);
    }
}
