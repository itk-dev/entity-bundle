<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AbstractITKDevEntityIntegrationTest extends KernelTestCase
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
        \assert($clock instanceof MockClock);
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

        self::assertSame($alice->getId(), $entity->getCreatedBy()?->getId());
        self::assertSame($alice->getId(), $entity->getModifiedBy()?->getId());

        $this->loginAs($bob);
        $entity->setLabel('mutated');
        $this->em->flush();

        self::assertSame($alice->getId(), $entity->getCreatedBy()?->getId());
        self::assertSame($bob->getId(), $entity->getModifiedBy()?->getId());
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
