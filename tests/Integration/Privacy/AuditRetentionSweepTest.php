<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Tests\Integration\Privacy;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use ITKDev\EntityBundle\Privacy\BulkAnonymizer;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\FixtureEntity;
use ITKDev\EntityBundle\Tests\Fixtures\Entity\TestUser;
use ITKDev\EntityBundle\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AuditRetentionSweepTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;
    private Connection $conn;
    private BulkAnonymizer $bulk;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->conn = $this->em->getConnection();
        $this->bulk = $container->get(BulkAnonymizer::class);
        $this->clock = $container->get('clock');

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testScrubsAuditRowsOlderThanDefaultRetention(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        // The auditor writes `created_at = new \DateTimeImmutable('now')` internally
        // (no injectable clock), so we backdate the audit rows by hand to simulate age.
        $old = new FixtureEntity();
        $old->setLabel('old-pii');
        $this->em->persist($old);
        $this->em->flush();
        $this->backdateAuditRows((string) $old->getId(), '2024-01-01 00:00:00');

        $fresh = new FixtureEntity();
        $fresh->setLabel('fresh-pii');
        $this->em->persist($fresh);
        $this->em->flush();
        // fresh stays at real-time created_at — well within P1Y.

        $this->clock->modify('2026-05-11 12:00:00');
        // Anonymize entities older than P10Y (so no entity anonymization triggers,
        // but the audit retention sweep still runs for every registered class).
        $report = $this->bulk->anonymizeOlderThan(new \DateInterval('P10Y'));

        self::assertSame(0, $report->rowsAnonymized);
        self::assertGreaterThanOrEqual(1, $report->auditRowsScrubbed, 'old audit rows should be touched');

        $oldRow = $this->conn->fetchAssociative(
            'SELECT diffs, ip, blame_user FROM test_fixture_entity_audit WHERE object_id = :oid',
            ['oid' => (string) $old->getId()],
        );
        self::assertIsArray($oldRow);
        $diffs = json_decode((string) $oldRow['diffs'], true);
        self::assertIsArray($diffs);
        self::assertNull($diffs['label']['new'], 'old PII values must be nulled');
        self::assertArrayHasKey('label', $diffs, 'field-key structure is kept for analytics');
        self::assertNull($oldRow['ip']);
        self::assertNull($oldRow['blame_user']);

        $freshRow = $this->conn->fetchAssociative(
            'SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid',
            ['oid' => (string) $fresh->getId()],
        );
        self::assertIsArray($freshRow);
        self::assertStringContainsString('fresh-pii', (string) $freshRow['diffs'], 'in-retention rows untouched');
    }

    public function testKeepsBlameIdAndStructuralColumns(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();
        $this->backdateAuditRows((string) $entity->getId(), '2024-01-01 00:00:00');

        $this->clock->modify('2026-05-11 12:00:00');
        $this->bulk->anonymizeOlderThan(new \DateInterval('P10Y'));

        $row = $this->conn->fetchAssociative(
            'SELECT blame_id, object_id, type, transaction_hash FROM test_fixture_entity_audit WHERE object_id = :oid',
            ['oid' => (string) $entity->getId()],
        );
        self::assertIsArray($row);

        self::assertSame((string) $alice->getId(), $row['blame_id']);
        self::assertSame((string) $entity->getId(), $row['object_id']);
        self::assertSame('insert', $row['type']);
        self::assertNotNull($row['transaction_hash']);
    }

    public function testIdempotentOnRerun(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $this->em->persist($entity);
        $this->em->flush();
        $this->backdateAuditRows((string) $entity->getId(), '2024-01-01 00:00:00');

        $this->clock->modify('2026-05-11 12:00:00');
        $first = $this->bulk->anonymizeOlderThan(new \DateInterval('P10Y'));
        $second = $this->bulk->anonymizeOlderThan(new \DateInterval('P10Y'));

        // The same rows are touched again — the sweep is unconditional on already-scrubbed
        // state. The DB content stays the same.
        self::assertSame($first->auditRowsScrubbed, $second->auditRowsScrubbed);
    }

    public function testDryRunCountsButLeavesAuditUntouched(): void
    {
        $alice = $this->aUser();
        $this->loginAs($alice);

        $entity = new FixtureEntity();
        $entity->setLabel('original');
        $this->em->persist($entity);
        $this->em->flush();
        $this->backdateAuditRows((string) $entity->getId(), '2024-01-01 00:00:00');

        $this->clock->modify('2026-05-11 12:00:00');
        $report = $this->bulk->anonymizeOlderThan(new \DateInterval('P10Y'), dryRun: true);
        self::assertGreaterThanOrEqual(1, $report->auditRowsScrubbed);

        $row = $this->conn->fetchAssociative(
            'SELECT diffs FROM test_fixture_entity_audit WHERE object_id = :oid',
            ['oid' => (string) $entity->getId()],
        );
        self::assertIsArray($row);
        self::assertStringContainsString('original', (string) $row['diffs'], 'dry-run must not mutate');
    }

    private function backdateAuditRows(string $objectId, string $when): void
    {
        $this->conn->executeStatement(
            'UPDATE test_fixture_entity_audit SET created_at = :when WHERE object_id = :oid',
            ['when' => $when, 'oid' => $objectId],
        );
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
