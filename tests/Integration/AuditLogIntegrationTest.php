<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration;

use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AuditLogIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Reader $auditReader;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->auditReader = $container->get(Reader::class);

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

    public function testInsertRecordsAuditRow(): void
    {
        $entity = new FixtureEntity();
        $entity->setLabel('audit-me');
        $this->em->persist($entity);
        $this->em->flush();

        $audits = $this->auditReader
            ->createQuery(FixtureEntity::class)
            ->execute();

        self::assertCount(1, $audits);
        self::assertSame('insert', $audits[0]->getType());
        self::assertSame((string) $entity->getId(), $audits[0]->getObjectId());
    }

    public function testUpdateRecordsAuditRowWithDiff(): void
    {
        $entity = new FixtureEntity();
        $entity->setLabel('before');
        $this->em->persist($entity);
        $this->em->flush();

        $entity->setLabel('after');
        $this->em->flush();

        $audits = $this->auditReader
            ->createQuery(FixtureEntity::class)
            ->execute();

        self::assertCount(2, $audits);
        // Reader returns newest first
        self::assertSame('update', $audits[0]->getType());
        $diffs = $audits[0]->getDiffs();
        self::assertArrayHasKey('label', $diffs);
        self::assertSame('before', $diffs['label']['old']);
        self::assertSame('after', $diffs['label']['new']);
    }

    public function testSoftDeleteRecordsAsUpdate(): void
    {
        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        $this->em->remove($entity);
        $this->em->flush();

        $audits = $this->auditReader
            ->createQuery(FixtureEntity::class)
            ->execute();

        self::assertCount(2, $audits);
        // soft delete is recorded as an update (deletedAt: null -> timestamp), not as a remove
        self::assertSame('update', $audits[0]->getType());
        $diffs = $audits[0]->getDiffs();
        self::assertArrayHasKey('deletedAt', $diffs);
        self::assertNotNull($diffs['deletedAt']['new']);
    }

    public function testCurrentUserIsRecordedAsBlame(): void
    {
        $alice = new TestUser();
        $this->em->persist($alice);
        $this->em->flush();

        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($alice, 'main', $alice->getRoles()));

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        $audits = $this->auditReader
            ->createQuery(FixtureEntity::class)
            ->execute();

        self::assertCount(1, $audits);
        self::assertSame((string) $alice->getId(), $audits[0]->getUserId());
    }
}
