<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

use DH\Auditor\Provider\Doctrine\DoctrineProvider;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AuditScrubber
{
    public function __construct(
        private EntityManagerInterface $em,
        private StrategyApplier $strategyApplier,
        private DoctrineProvider $auditProvider,
    ) {
    }

    /**
     * Walk every audit row for $entity and apply $rules to the JSON `diffs` column,
     * scrubbing both `old` and `new` values for every anonymizable property.
     *
     * Must be called after the entity has been flushed, so the anonymization's own audit
     * row exists and gets scrubbed in the same pass.
     *
     * @param list<AnonymizationRule> $rules
     */
    public function scrubEntityHistory(object $entity, array $rules): void
    {
        if ([] === $rules) {
            return;
        }

        $conn = $this->em->getConnection();
        $meta = $this->em->getClassMetadata($entity::class);
        $auditTable = $meta->getTableName().'_audit';
        $quotedTable = $conn->quoteIdentifier($auditTable);
        $objectId = (string) $meta->getIdentifierValues($entity)[$meta->getSingleIdentifierFieldName()];

        $rows = $conn->fetchAllAssociative(
            sprintf('SELECT id, diffs FROM %s WHERE object_id = :oid', $quotedTable),
            ['oid' => $objectId],
        );

        foreach ($rows as $row) {
            $diffs = json_decode((string) $row['diffs'], true);
            if (!\is_array($diffs)) {
                continue;
            }

            $changed = false;
            foreach ($rules as $rule) {
                $key = $rule->property;
                if (!isset($diffs[$key]) || !\is_array($diffs[$key])) {
                    continue;
                }

                foreach (['old', 'new'] as $side) {
                    if (!\array_key_exists($side, $diffs[$key])) {
                        continue;
                    }
                    if (null === $diffs[$key][$side]) {
                        continue;
                    }
                    $diffs[$key][$side] = $this->strategyApplier->apply(
                        $rule->strategy,
                        $diffs[$key][$side],
                        $rule->replacement,
                    );
                    $changed = true;
                }
            }

            if ($changed) {
                $conn->update(
                    $auditTable,
                    ['diffs' => json_encode($diffs, JSON_THROW_ON_ERROR)],
                    ['id' => (int) $row['id']],
                );
            }
        }
    }

    /**
     * NULL out the `ip` column on every audit row where the subject was the actor
     * (blame_id matches). IPs are personal data under GDPR Recital 30.
     */
    public function scrubSubjectBlame(string $subjectId): void
    {
        $conn = $this->em->getConnection();
        foreach ($this->discoverAuditTables() as $auditTable) {
            $conn->executeStatement(
                sprintf('UPDATE %s SET ip = NULL WHERE blame_id = :id', $conn->quoteIdentifier($auditTable)),
                ['id' => $subjectId],
            );
        }
    }

    /**
     * @param class-string $class
     */
    public function countAuditOlderThan(string $class, \DateTimeImmutable $threshold): int
    {
        $conn = $this->em->getConnection();
        $meta = $this->em->getClassMetadata($class);
        $quotedTable = $conn->quoteIdentifier($meta->getTableName().'_audit');

        return (int) $conn->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE created_at < :threshold', $quotedTable),
            ['threshold' => $threshold->format('Y-m-d H:i:s')],
        );
    }

    /**
     * Apply the retention sweep to every audit row for $class older than $threshold:
     * null every old/new value in the `diffs` JSON (keeping the field-key structure
     * for analytics), and null `ip` / `blame_user*`. `blame_id` is kept.
     *
     * Idempotent: rows already wiped retain their null state on subsequent runs.
     *
     * @param class-string $class
     *
     * @return int number of audit rows touched
     */
    public function scrubAuditOlderThan(string $class, \DateTimeImmutable $threshold): int
    {
        $conn = $this->em->getConnection();
        $meta = $this->em->getClassMetadata($class);
        $auditTable = $meta->getTableName().'_audit';
        $quotedTable = $conn->quoteIdentifier($auditTable);

        $rows = $conn->fetchAllAssociative(
            sprintf('SELECT id, diffs FROM %s WHERE created_at < :threshold', $quotedTable),
            ['threshold' => $threshold->format('Y-m-d H:i:s')],
        );

        $touched = 0;
        foreach ($rows as $row) {
            $diffs = json_decode((string) $row['diffs'], true);
            $cleared = $this->clearDiffValues(\is_array($diffs) ? $diffs : []);

            $conn->update($auditTable, [
                'diffs' => json_encode($cleared, JSON_THROW_ON_ERROR),
                'ip' => null,
                'blame_user' => null,
                'blame_user_fqdn' => null,
                'blame_user_firewall' => null,
            ], ['id' => (int) $row['id']]);

            ++$touched;
        }

        return $touched;
    }

    /**
     * @param array<string, mixed> $diffs
     *
     * @return array<string, mixed>
     */
    private function clearDiffValues(array $diffs): array
    {
        $out = [];
        foreach ($diffs as $field => $value) {
            if (!\is_array($value)) {
                $out[$field] = $value;
                continue;
            }
            $shape = [];
            foreach (['old', 'new'] as $side) {
                if (\array_key_exists($side, $value)) {
                    $shape[$side] = null;
                }
            }
            $out[$field] = [] !== $shape ? $shape : $value;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function discoverAuditTables(): array
    {
        $tables = [];
        foreach ($this->auditProvider->getConfiguration()->getEntities() as $entry) {
            if (!\is_array($entry) || !isset($entry['audit_table_name'])) {
                continue;
            }
            $tables[] = (string) $entry['audit_table_name'];
        }

        return $tables;
    }
}
