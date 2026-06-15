# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`itk-dev/entity-bundle` is a Symfony bundle (type: `symfony-bundle`) — not an application. It packages cross-cutting Doctrine entity concerns (ULID id, timestamps, blame, soft-delete, archivable, audit-log auto-wiring, GDPR anonymization) behind opt-in traits + config flags. It is consumed by host Symfony apps, typically via a Composer path repository.

Requires PHP 8.4+, Symfony 7.4 or 8.0, Doctrine ORM 3, and damienharper/auditor-bundle 6.3.

## Common commands

```bash
composer install                    # install deps (only when the bundle is being tested standalone)
vendor/bin/phpunit                  # run the full test suite (Unit + Integration)
vendor/bin/phpunit tests/Unit       # one suite
vendor/bin/phpunit --filter SoftDeleteListenerTest   # one test class
vendor/bin/phpunit tests/Integration/Privacy/AnonymizerTest.php   # one file
```

PHPUnit is configured to `failOnNotice` and `failOnWarning` — deprecations and notices fail tests; do not silence them.

There is no PHPStan / Psalm / php-cs-fixer config in the repo. Don't invent linter commands; if static analysis is needed, ask the user first.

## Test harness

- `tests/App/Kernel.php` is a `MicroKernelTrait` test kernel; `phpunit.xml.dist` points `KERNEL_CLASS` at it.
- `tests/bootstrap.php` looks for `vendor/autoload.php` in the bundle first, then in `../../../vendor` — so the test suite also runs when the bundle is consumed as a path repo from a parent app.
- Integration tests expect a database reachable at the `DATABASE_URL` baked into `phpunit.xml.dist` (`mysql://db:db@database:3306/db`, served by the `database` service in `docker-compose.yml`, which runs MariaDB 11.4 by default). To run against PostgreSQL instead, layer the opt-in override: `docker compose -f docker-compose.yml -f docker-compose.postgres.yml up` — that file swaps the `database` service for `postgres:16` and overrides `DATABASE_URL` on the `phpfpm` service. When iterating locally outside Docker, override `DATABASE_URL` rather than editing the file.
- The `phpfpm` service runs PHP 8.4 by default. To run the suite on PHP 8.5, layer the opt-in override: `docker compose -f docker-compose.yml -f docker-compose.php85.yml up` — that file swaps the `phpfpm` image for `itkdev/php8.5-fpm:latest`. The two PHP overrides compose: pass both `-f docker-compose.postgres.yml -f docker-compose.php85.yml` to run PHP 8.5 against Postgres.
- Test fixtures live in `tests/Fixtures/Entity/` and are mapped to the `TestFixtures` Doctrine alias by `tests/App/config/packages/doctrine.yaml`.

## Architecture — the bits that span multiple files

### The `#[ITKDevEntity]` discovery contract

Every feature in this bundle (audit auto-registration, anonymization rule discovery, listener targeting) keys off the `#[ITKDevEntity]` attribute (`src/Attribute/`). Two ways to apply it:

1. Extend `ITKDev\EntityBundle\Entity\AbstractITKDevEntity` (gives you a ULID id + the attribute in one step).
2. Annotate any Doctrine entity directly with `#[ITKDevEntity]` — works when the entity has its own id strategy or base class.

The attribute is honoured up the inheritance chain. Discovery walks `entity_paths` (default `%kernel.project_dir%/src/Entity`) and skips abstract classes and anything without `#[ITKDevEntity]` on itself or an ancestor.

### Feature wiring — everything is opt-in twice

For every feature (timestampable, blameable, soft-delete, archivable, audit, anonymization) there are **two** opt-ins, and both are required:

1. **Per-entity**: implement an interface + use a trait (e.g. `SoftDeletableInterface` + `SoftDeletableTrait`).
2. **Bundle-wide**: flip `itk_dev_entity.<feature>.enabled: true` in config.

If the per-entity opt-in is present but the bundle flag is off, the entity still carries the columns from the trait, but the listener/filter/command is never registered — so behavior silently degrades to a hard delete, no timestamps written, etc. When debugging "the trait is there but it doesn't work", check the bundle config first.

The full config reference (including `user_class`, `entity_paths`, `audit.retention`, retention overrides) is in `README.md`; don't duplicate it elsewhere.

### `DependencyInjection/ITKDevEntityExtension.php` is the brain

This Extension does the heavy lifting that ties the pieces together:

- **Reflection walk** over `entity_paths` to find `#[ITKDevEntity]` classes, then per-class to find `#[Auditable]`, `#[AuditIgnore]`, and `#[Anonymize]` property attributes.
- **`prepend()`**: configures `damienharper/auditor-bundle` with the discovered auditable entities + ignored columns, and merges config-driven `audit.entities` / `audit.ignored_columns` (the escape hatch for third-party entities you can't annotate).
- **`load()`**: dynamically registers Doctrine listeners (`TimestampableListener`, `BlameableListener`, `SoftDeleteListener`) and filters (`soft_delete`, `archivable`) based on the per-feature `enabled` flag. The `archivable` filter is registered **disabled** — callers enable it per-request (e.g. a `?showArchived=1` listener).
- For anonymization, the discovered `#[Anonymize]` strategies are merged with `itk_dev_entity.anonymization.rules` from config. **Config wins over the attribute** when the same property is named in both (config is the explicit override).

If you're adding a new opt-in feature, the pattern is: interface + trait under `src/Entity/`, listener/filter under `src/Doctrine/`, config tree node in `DependencyInjection/Configuration.php`, and conditional registration in `ITKDevEntityExtension::load()`.

### `src/` top-level map

- `Attribute/` — the `#[ITKDevEntity]` marker
- `Audit/Attribute/` — `#[Auditable]` and `#[AuditIgnore]`
- `Privacy/` — `Anonymizer`, `BulkAnonymizer`, `StaleEntityFinder`, `StrategyApplier`, and the `#[Anonymize]` attribute + `Strategy` enum (`NullValue`, `Redact`, `Hash`, `Pseudonymize`)
- `Entity/` — `AbstractITKDevEntity` (ULID id mapped superclass), per-feature `*Interface` contracts under `Entity/Contract/`, per-feature `*Trait` under `Entity/Trait/`
- `Doctrine/` — listeners (`onFlush` for timestamps/blame, intercepts `remove()` for soft-delete) and SQL filters (`soft_delete`, `archivable`)
- `DependencyInjection/` — `Configuration` (config tree) and `ITKDevEntityExtension` (discovery + wiring; see above)
- `Command/` — `privacy:anonymize <ulid>` (right-to-erasure) and `privacy:anonymize-stale --older-than=PXX` (retention sweep). Audit-row deletion is delegated to dh_auditor's own `audit:clean`; do not reimplement it.

### Blame and `user_class`

The bundle types `createdBy`/`modifiedBy` as `?UserInterface`. The host app's concrete user class is resolved at runtime via Doctrine `resolve_target_entities`, configured by `itk_dev_entity.user_class`. That config key is required whenever `audit.enabled` or `blameable.enabled` is true, and is also used by `privacy:anonymize` to find the subject row.
