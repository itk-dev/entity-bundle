<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AuditLogIntegrationTest extends IntegrationTestCase
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
        self::assertSame('insert', self::entryField($audits[0], 'type'));
        self::assertSame((string) $entity->getId(), self::entryField($audits[0], 'objectId'));
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
        self::assertSame('update', self::entryField($audits[0], 'type'));
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
        self::assertSame('update', self::entryField($audits[0], 'type'));
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
        self::assertSame((string) $alice->getId(), self::entryField($audits[0], 'userId'));
    }

    /**
     * Read a field off an auditor Entry across both supported auditor majors.
     *
     * auditor 3 exposes these fields as getters; auditor 4 replaced them with PHP 8.4 property
     * hooks and dropped the getters. `getDiffs()` is the exception — it survived as a method on
     * both, so call sites for that one are left alone.
     */
    private static function entryField(object $entry, string $field): mixed
    {
        $getter = 'get'.ucfirst($field);
        if (method_exists($entry, $getter)) {
            return $entry->{$getter}();
        }

        // Fail loudly if a future major renames the field, rather than letting it surface as an
        // undefined-property warning from somewhere further down the assertion.
        if (!property_exists($entry, $field)) {
            throw new \LogicException(sprintf('Auditor Entry exposes neither %s() nor $%s.', $getter, $field));
        }

        return $entry->{$field};
    }
}
