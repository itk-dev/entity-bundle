# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-09-25

### Changed

- Widened the `damienharper/auditor-bundle` requirement from `^6.3` to `^6.3 || ^7.2`, so consumers
  can move to auditor-bundle 7 / auditor 4 without waiting on this bundle. Symfony 7.4 apps keep
  resolving auditor 6; Symfony 8 apps resolve auditor 7. Nothing in the bundle's own API changes.
  The upper floor is `^7.2` rather than `^7.0` because that is what issue #2 proposed, what was
  verified upstream against a Symfony 8.1 consumer, and the only 7.x resolution this bundle's CI
  exercises; auditor-bundle also only took `damienharper/auditor-doctrine-provider` as a direct
  dependency from 7.1 onward. 7.0 and 7.1 are untested here, not known-broken.
  **Upgrading:** a host app moving an existing database from auditor 6 to auditor 7 must run
  `bin/console audit:schema:update --force` once — auditor 4 adds a nullable JSON `extra_data`
  column to every audit table, which fresh installs get from the schema listener but existing
  databases do not. See "Upgrading to auditor-bundle 7" in `README.md`.
- `.github/workflows/phpunit.yaml` now varies the auditor major as well as the database: auditor 7
  (highest resolution) on MariaDB and PostgreSQL, plus auditor 6 (`--prefer-lowest`, which also
  pins Symfony 7.4) on MariaDB.
- `AuditLogIntegrationTest` reads auditor `Entry` fields through a small version-agnostic helper —
  auditor 4 dropped the `getType()` / `getObjectId()` / `getUserId()` getters in favour of PHP 8.4
  property hooks.

### Fixed

The first two were surfaced by the new lower-bound CI leg; all three are pre-existing and also
affect `develop`.

- Declared `symfony/doctrine-bridge` (`^7.4 || ^8.0`) as a direct requirement. The bundle imports
  `Symfony\Bridge\Doctrine\Types\UlidType` in `SubjectAnonymizer` but never required the package,
  so its floor was set only by transitive constraints — which allowed a 6.4 bridge to be installed
  alongside Symfony 7.4 http-kernel, a combination that fatals on a `WarmableInterface::warmUp()`
  signature mismatch.
- Declared `damienharper/auditor` (`^3.4 || ^4.0`) as a direct requirement. The bundle imports
  `DH\Auditor\Provider\Doctrine\*` directly, and auditor-bundle 6.3's own `^3.2` constraint is too
  loose: with auditor core 3.2 the bundle passes `viewer` as an array where core still expects a
  bool, so the Doctrine provider fails to construct.
  Note that this does not fully close the gap under auditor 4, where those provider classes live in
  `damienharper/auditor-doctrine-provider` rather than in core. That package is deliberately left
  undeclared — it requires `damienharper/auditor: ^4.0`, so requiring it would force auditor 4 on
  every consumer and break the auditor-6 leg — and is pulled in by auditor-bundle 7.x instead.
  The constraint is therefore not expressible in Composer; see the note on `AuditScrubber`.
- The PHPStan workflow never generated the test container it analyses against, so the job failed
  on any cold checkout with `Container ... KernelTestDebugContainer.xml does not exist`. That file
  is written when the test kernel boots, and phpstan-symfony hashes it *before* PHPStan executes
  `bootstrapFiles` — so the bundled `phpstan-bootstrap.php` could never be what created it. The
  workflow and `task lint:phpstan` now boot the kernel as an explicit step first.

## [0.1.1] - 2026-06-15

- Cleaned up README.

## [0.1.0] - 2026-06-15

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
