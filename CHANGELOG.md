# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `#[ITKDevEntity]` attribute and `AbstractITKDevEntity` ULID-id mapped superclass — the single discovery contract that
  every other feature keys off.
- Timestampable trait/listener that fills `createdAt` / `updatedAt` on flush.
- Blameable trait/listener that resolves the current `UserInterface` (via the host app's `user_class`) into
  `createdBy` / `modifiedBy`.
- Soft-delete trait, `onFlush` listener (intercepts `EntityManager::remove()` and writes `deletedAt`), and a
  `soft_delete` Doctrine SQL filter that hides deleted rows by default. A second `remove()` performs a hard delete.
- Archivable trait and an `archivable` Doctrine SQL filter, registered disabled so callers enable it per request
  (e.g. via a `?showArchived=1` listener).
- Auditor-bundle auto-wiring: reflection over `entity_paths` discovers `#[ITKDevEntity]` classes and their
  `#[Auditable]` / `#[AuditIgnore]` property attributes, then prepends the corresponding configuration onto
  `damienharper/auditor-bundle`. Third-party entities can be registered via `audit.entities` /
  `audit.ignored_columns` config.
- GDPR anonymization: `#[Anonymize]` property attribute with a `Strategy` enum (`NullValue`, `Redact`, `Hash`,
  `Pseudonymize`), `StrategyApplier`, `Anonymizer`, `BulkAnonymizer`, and `StaleEntityFinder` services. Per-property
  rules from config override the attribute when both are present.
- `privacy:anonymize <ulid>` console command for right-to-erasure of a single subject and all rows that reference it.
- `privacy:anonymize-stale --older-than=PXX` console command for retention-driven bulk anonymization. Audit-row
  cleanup is delegated to dh_auditor's own `audit:clean`.
- Bundle configuration tree (`itk_dev_entity`) with `enabled` flags per feature, `user_class`, `entity_paths`,
  `audit.retention`, and `anonymization.rules` — every feature is opt-in twice (per-entity interface+trait and the
  bundle-wide flag).
- PHP 8.4+ / Symfony 7.4 or 8.0 / Doctrine ORM 3 support, tested against MariaDB 11.4 and PostgreSQL 16.
