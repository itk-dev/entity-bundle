<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use DH\Auditor\Provider\Doctrine\Persistence\Reader\Reader;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Privacy\AnonymizationRule;
use ITKDev\EntityBundle\Privacy\AuditScrubber;
use ITKDev\EntityBundle\Privacy\Strategy;
use ITKDev\EntityBundle\Privacy\SubjectAnonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use ITKDev\EntityBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AuditScrubberTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;
    private Connection $conn;
    private SubjectAnonymizer $subjectAnonymizer;
    private Reader $auditReader;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->conn = $this->em->getConnection();
        $this->subjectAnonymizer = $container->get(SubjectAnonymizer::class);
        $this->auditReader = $container->get(Reader::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testAnonymizationScrubsTheDiffItJustWrote(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('alice-email@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $this->subjectAnonymizer->anonymize($alice);

        // The audit row written by the anonymization itself must NOT contain the original value
        $rows = $this->conn->fetchAllAssociative(
            'SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid ORDER BY id',
            ['oid' => (string) $entity->getId()],
        );

        foreach ($rows as $row) {
            $raw = $row['diffs'];
            self::assertStringNotContainsString(
                'alice-email@example.com',
                $raw,
                'no audit row may retain the pre-anonymization value',
            );
        }
    }

    public function testScrubsPriorHistoryToo(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('original-pii');
        $this->em->persist($entity);
        $this->em->flush();

        $entity->setLabel('updated-pii');
        $this->em->flush();

        $entity->setLabel('latest-pii');
        $this->em->flush();

        // Sanity: history has all three values before scrub
        $allDiffs = $this->concatAuditDiffs((string) $entity->getId());
        self::assertStringContainsString('original-pii', $allDiffs);
        self::assertStringContainsString('updated-pii', $allDiffs);

        $this->subjectAnonymizer->anonymize($alice);

        $allDiffs = $this->concatAuditDiffs((string) $entity->getId());
        self::assertStringNotContainsString('original-pii', $allDiffs);
        self::assertStringNotContainsString('updated-pii', $allDiffs);
        self::assertStringNotContainsString('latest-pii', $allDiffs);
    }

    private function concatAuditDiffs(string $objectId): string
    {
        $rows = $this->conn->fetchAllAssociative(
            'SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid',
            ['oid' => $objectId],
        );

        return implode(' ', array_map(static fn (array $row): string => (string) $row['diffs'], $rows));
    }

    public function testNullsIpOnAuditRowsWhereSubjectWasActor(): void
    {
        $alice = $this->aUser();
        $bob = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();

        // Simulate an IP being recorded (CLI tests don't capture one): force it on the
        // audit rows linked to alice AND bob, then verify only alice's are scrubbed.
        $this->conn->executeStatement(
            'UPDATE test_fixture_entity_audit SET ip = :ip WHERE blame_id = :id',
            ['ip' => '192.0.2.10', 'id' => (string) $alice->getId()],
        );
        $this->conn->executeStatement(
            "INSERT INTO test_fixture_entity_audit (type, object_id, diffs, blame_id, blame_user, ip, created_at) VALUES ('update', :oid, '{}', :bobid, 'bob', :ip, NOW())",
            ['oid' => (string) $entity->getId(), 'bobid' => (string) $bob->getId(), 'ip' => '198.51.100.20'],
        );

        $this->subjectAnonymizer->anonymize($alice);

        $aliceIps = $this->conn->fetchAllAssociative(
            'SELECT ip FROM test_fixture_entity_audit WHERE blame_id = :id',
            ['id' => (string) $alice->getId()],
        );
        foreach ($aliceIps as $row) {
            self::assertNull($row['ip'], 'IPs from rows where the subject was the actor must be nulled');
        }

        $bobIp = $this->conn->fetchOne(
            'SELECT ip FROM test_fixture_entity_audit WHERE blame_id = :id',
            ['id' => (string) $bob->getId()],
        );
        self::assertSame('198.51.100.20', $bobIp, "other users' IPs must stay intact");
    }

    public function testScrubEntityHistoryIsANoopWhenNoRules(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('keepme');
        $this->em->persist($entity);
        $this->em->flush();

        $before = $this->concatAuditDiffs((string) $entity->getId());
        $this->scrubber()->scrubEntityHistory($entity, []);
        $after = $this->concatAuditDiffs((string) $entity->getId());

        self::assertSame($before, $after);
        self::assertStringContainsString('keepme', $after);
    }

    public function testScrubEntityHistorySkipsRowsWithInvalidJsonOrNullSideValues(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('pii');
        $this->em->persist($entity);
        $this->em->flush();

        // Row 1: diffs is not valid JSON. Row 2: label side values are null.
        // Both must be left untouched (no error, no DB update) by the scrubber.
        $this->conn->executeStatement(
            "INSERT INTO test_fixture_entity_audit (type, object_id, diffs, blame_id, created_at) VALUES ('update', :oid, 'null', :uid, NOW())",
            ['oid' => (string) $entity->getId(), 'uid' => (string) $alice->getId()],
        );
        $this->conn->executeStatement(
            "INSERT INTO test_fixture_entity_audit (type, object_id, diffs, blame_id, created_at) VALUES ('update', :oid, :diffs, :uid, NOW())",
            [
                'oid' => (string) $entity->getId(),
                'uid' => (string) $alice->getId(),
                'diffs' => json_encode(['label' => ['old' => null, 'new' => null]], JSON_THROW_ON_ERROR),
            ],
        );

        $this->scrubber()->scrubEntityHistory($entity, [
            new AnonymizationRule('label', Strategy::Redact, '[REDACTED]'),
        ]);

        $rows = $this->conn->fetchAllAssociative(
            'SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid AND type = :t',
            ['oid' => (string) $entity->getId(), 't' => 'update'],
        );
        $diffs = array_column($rows, 'diffs');
        self::assertContains('null', $diffs);
        self::assertContains('{"label":{"old":null,"new":null}}', $diffs);
    }

    public function testScrubAuditOlderThanPreservesScalarDiffEntries(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('pii');
        $this->em->persist($entity);
        $this->em->flush();

        // Inject a row whose `diffs` JSON has a scalar (non-shape) entry. clearDiffValues
        // must copy the scalar through verbatim rather than treat it as an old/new shape.
        $this->conn->executeStatement(
            "INSERT INTO test_fixture_entity_audit (type, object_id, diffs, blame_id, created_at) VALUES ('insert', :oid, :diffs, :uid, '2020-01-01 00:00:00')",
            [
                'oid' => (string) $entity->getId(),
                'uid' => (string) $alice->getId(),
                'diffs' => json_encode(['note' => 'scalar-marker', 'label' => ['old' => 'pii', 'new' => null]], JSON_THROW_ON_ERROR),
            ],
        );

        $touched = $this->scrubber()->scrubAuditOlderThan(FixtureEntity::class, new \DateTimeImmutable('2025-01-01'));
        self::assertGreaterThan(0, $touched);

        $row = $this->conn->fetchAssociative(
            "SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid AND created_at = '2020-01-01 00:00:00'",
            ['oid' => (string) $entity->getId()],
        );
        self::assertIsArray($row);
        self::assertStringContainsString('scalar-marker', (string) $row['diffs']);
    }

    public function testLeavesNonAnonymizableFieldsIntact(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $this->em->persist($entity);
        $this->em->flush();

        $this->subjectAnonymizer->anonymize($alice);

        // The audit row records both `label` (anonymizable, scrubbed) and other fields
        // like `updatedAt`/`anonymizedAt`/`modifiedBy` (not anonymizable, must be retained).
        $audits = $this->auditReader->createQuery(FixtureEntity::class)->execute();
        self::assertNotEmpty($audits);

        $allDiffs = '';
        foreach ($audits as $audit) {
            $allDiffs .= json_encode($audit->getDiffs());
        }

        self::assertStringContainsString('anonymizedAt', $allDiffs, 'anonymizedAt change is recorded and kept');
        self::assertStringContainsString('updatedAt', $allDiffs, 'updatedAt change is recorded and kept');
    }

    private function scrubber(): AuditScrubber
    {
        return self::getContainer()->get(AuditScrubber::class);
    }

    private function aUser(): TestUser
    {
        $user = new TestUser();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function loginAs(TestUser $user): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }
}
