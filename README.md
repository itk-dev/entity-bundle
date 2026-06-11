# itk-dev/entity-bundle

Cross-cutting entity foundation for Symfony 7.4 / 8.0 and Doctrine ORM 3 projects.

## Requirements

- PHP **>= 8.4**
- Symfony **7.4 or 8.0** (`framework-bundle`, `security-bundle`, `clock`, `finder`, `uid`)
- Doctrine ORM **^3.0** with `doctrine/doctrine-bundle` **^2.13 or ^3.0**
- [`damienharper/auditor-bundle`](https://github.com/DamienHarper/auditor-bundle) **^6.3**
  (only relevant when `audit.enabled` is on)

## Installation

The bundle is not published on Packagist. Add it to a Symfony project as a Composer VCS repository:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/itk-dev/entity-bundle.git" }
  ],
  "require": {
    "itk-dev/entity-bundle": "^1.0"
  }
}
```

Then:

```bash
composer require itk-dev/entity-bundle:^1.0
```

Enable the bundle in `config/bundles.php`:

```php
return [
    // ...
    ITKDev\EntityBundle\ITKDevEntityBundle::class => ['all' => true],
];
```

## What you get

Every domain entity in your project carries `#[ITKDev\EntityBundle\Attribute\ITKDevEntity]`, which is the signal the
bundle uses to scan it for the opt-in markers below (auditable, anonymization, audit-ignored fields, …).

Two ways to apply it:

```php
// 1. Extend AbstractITKDevEntity — convenience: ULID id + #[ITKDevEntity] in one step.
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;

#[ORM\Entity]
class Project extends AbstractITKDevEntity { /* ... */ }
```

```php
// 2. Add the attribute directly — works on any Doctrine entity, even ones with
//    a different id strategy or a different base class.
use ITKDev\EntityBundle\Attribute\ITKDevEntity;

#[ORM\Entity]
#[ITKDevEntity]
class LegacyThing { /* your own id, your own base class */ }
```

`#[ITKDevEntity]` is honoured along the parent chain — so subclasses of `AbstractITKDevEntity` inherit it without
re-declaring. Discovery skips abstract classes and any class without the attribute on itself or an ancestor.

Everything else (timestamps, blame, soft-delete, archivable, anonymization status, audit logging) is opt-in per entity
_and_ gated by a bundle config flag, so tables only carry the columns they actually need.

### Opt-in: timestamps

Per entity:

```php
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;

#[ORM\Entity]
class Project extends AbstractITKDevEntity implements TimestampableInterface
{
    use TimestampableTrait;
    // ...
}
```

Bundle config:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  timestampable:
    enabled: true
```

When enabled, the `TimestampableListener` (Doctrine `onFlush`, `ClockInterface`-injected, UTC) sets `createdAt` on
insert and `updatedAt` on every flush.

### Opt-in: blame

Per entity:

```php
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\BlameableInterface;
use ITKDev\EntityBundle\Entity\Trait\BlameableTrait;

#[ORM\Entity]
class Project extends AbstractITKDevEntity implements BlameableInterface
{
    use BlameableTrait;
    // ...
}
```

Bundle config:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  blameable:
    enabled: true
```

When enabled, the `BlameableListener` (Doctrine `onFlush`) sets `createdBy` on insert and `modifiedBy` on every flush
from `Symfony\Bundle\SecurityBundle\Security`. Bundle code types these as `?UserInterface`; the concrete class is
resolved at runtime via Doctrine `resolve_target_entities`, configured by `itk_dev_entity.user_class`.

### Opt-in: archivable

Archivable is **off by default** and opt-in per entity, so tables that don't need it stay schema-clean.

To opt an entity in:

```php
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\ArchivableInterface;
use ITKDev\EntityBundle\Entity\Trait\ArchivableTrait;

#[ORM\Entity]
class Project extends AbstractITKDevEntity implements ArchivableInterface
{
    use ArchivableTrait;
    // ...
}
```

Then enable the bundle-level wiring so the Doctrine filter gets registered:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  archivable:
    enabled: true
```

`$entity->archive($at)` sets `archivedAt`. The `archivable` SQL filter is registered **disabled** — toggle it
per-request (typically via a `?showArchived=1` listener that calls `$em->getFilters()->enable('archivable')`) to hide
archived rows from `findAll`/`find`.

If `archivable.enabled` is left off, the filter is never registered — entities that use `ArchivableTrait` will still
have the `archived_at` column and the `archive()`/`unarchive()` methods, but enabling the filter will throw.

### Opt-in: soft delete

Soft delete is **off by default** and opt-in per entity, so tables that don't need it stay schema-clean.

To opt an entity in:

```php
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;
use ITKDev\EntityBundle\Entity\Trait\SoftDeletableTrait;

#[ORM\Entity]
class Project extends AbstractITKDevEntity implements SoftDeletableInterface
{
    use SoftDeletableTrait;
    // ...
}
```

Then enable the bundle-level wiring so the listener and Doctrine filter get registered:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  soft_delete:
    enabled: true
```

With both in place, `$em->remove($entity)` writes `deletedAt` and cancels the scheduled `DELETE`. A SQL filter
(`soft_delete`, enabled by default once the feature is on) hides deleted rows from `findAll`/`find`. Disable per-query
with `$em->getFilters()->disable('soft_delete')`. A second `remove()` on an already-soft-deleted row performs a real
`DELETE`.

If `soft_delete.enabled` is left off, the listener and filter are never registered — entities that use
`SoftDeletableTrait` will still have the `deleted_at` column, but `remove()` performs a hard delete.

## Audit log (opt-in)

Audit is **off by default**. Enable it in bundle config:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  audit:
    enabled: true
```

When enabled, the bundle scans every concrete `AbstractITKDevEntity` subclass under `entity_paths` and registers the
ones marked with `#[Auditable]` with [damienharper/auditor-bundle](https://github.com/DamienHarper/auditor-bundle). A
sibling `<table>_audit` table is created per registered entity, recording every insert/update/association-change with
the actor and a field-level diff.

```php
use ITKDev\EntityBundle\Audit\Attribute\Auditable;

#[ORM\Entity]
#[Auditable]
class Project extends AbstractITKDevEntity
{
    // ...
}
```

Auditing is opt-in at the class level — entities without `#[Auditable]` get no `*_audit` table and produce no audit
rows. This keeps the audit surface (and DB clutter) tight to the entities you actually need to trace.

### Excluding sensitive fields from the audit log

To keep secrets (passwords, tokens, etc.) from ever reaching `<table>_audit`, annotate the property with
`#[AuditIgnore]`:

```php
use ITKDev\EntityBundle\Audit\Attribute\AuditIgnore;

#[ORM\Entity]
class User extends AbstractITKDevEntity
{
    #[ORM\Column]
    #[AuditIgnore]
    private string $password;
}
```

The bundle's discovery picks these up at compile time and adds them to dh_auditor's per-entity `ignored_columns` config,
so changes to those properties produce no audit row entries at all.

`#[AuditIgnore]` is independent from `#[Anonymize]`: the former says "never write to the audit log in the first place";
the latter says "this is PII that may need to be scrubbed retroactively". Use both on the same property if you want
belt-and-braces (never logged AND scrubbed on subject erasure).

### Third-party entities (entities you can't annotate)

PHP attributes have to live in the source file, so `#[Auditable]`, `#[AuditIgnore]`, and `#[Anonymize]` only work on
classes you own. For entities that come from another package, use the config escape hatches:

```yaml
itk_dev_entity:
  audit:
    enabled: true
    entities:
      - Vendor\Bundle\Entity\Thing # additive to #[Auditable] discoveries
    ignored_columns:
      Vendor\Bundle\Entity\Thing: [password, token] # additive to #[AuditIgnore]
  anonymization:
    enabled: true
    rules:
      Vendor\Bundle\Entity\Thing:
        email: { strategy: pseudonymize }
        phone: { strategy: redact, replacement: "[REDACTED]" }
```

All three keys are additive to attribute-based discovery — first-party entities you already annotate are unaffected. For
the same property name in `anonymization.rules`, the config wins over the attribute (treat config as the explicit
override).

`strategy` accepts `null`, `redact`, `hash`, or `pseudonymize` (the values of the `Strategy` enum).

### Pruning old audit rows

The auditor bundle already ships `audit:clean`. Use it as-is — the entity-bundle does not wrap or replace it. A hard
`DELETE` runs against each `<table>_audit` for rows older than the given retention window:

```bash
# Delete audit rows older than 30 days across every audited entity
bin/console audit:clean P30D

# Preview without touching the database
bin/console audit:clean P30D --dry-run --dump-sql

# Limit to specific entities (or exclude some)
bin/console audit:clean P12M --include="App\Entity\Project" --include="App\Entity\Issue"
bin/console audit:clean P12M --exclude="App\Entity\AuditCriticalThing"

# Use an absolute cutoff date instead of an interval
bin/console audit:clean --date=2024-01-01

# Skip the interactive confirmation (cron-friendly)
bin/console audit:clean P30D --no-confirm
```

`keep` defaults to `P12M` if omitted. Run `bin/console audit:clean --help` for the full option list.

If you also need PII scrubbing on retained audit rows (rather than deletion), see `privacy:anonymize-stale` below.

## Privacy / anonymization (opt-in)

Anonymization is **off by default**. Enable it in bundle config:

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  anonymization:
    enabled: true
```

When disabled, none of the privacy services or commands are registered. When enabled, opt entities in by implementing
`AnonymizationStatusInterface`, using `AnonymizationStatusTrait` (adds the `anonymizedAt` column for idempotency), and
annotating PII properties:

```php
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\AnonymizationStatusInterface;
use ITKDev\EntityBundle\Entity\Trait\AnonymizationStatusTrait;
use ITKDev\EntityBundle\Privacy\Attribute\Anonymize;
use ITKDev\EntityBundle\Privacy\Strategy;

#[ORM\Entity]
class User extends AbstractITKDevEntity implements AnonymizationStatusInterface
{
    use AnonymizationStatusTrait;

    #[ORM\Column]
    #[Anonymize(strategy: Strategy::Pseudonymize)]
    private string $email;
    // ...
}
```

Each PII field gets `#[ITKDev\EntityBundle\Privacy\Attribute\Anonymize(strategy: Strategy::Redact)]`. Strategies:
`NullValue`, `Redact`, `Hash`, `Pseudonymize` (sha1 with a `kernel.secret`-derived pepper). The bundle scans these at
compile time and ships two console commands:

- `bin/console privacy:anonymize <subjectUlid>` — right-to-erasure: scrubs PII on the subject's User row + every row
  that references them via a `ManyToOne(UserInterface)` association, plus rewrites the corresponding audit history.
  Wrapped in a transaction.
- `bin/console privacy:anonymize-stale --older-than=P2Y [--dry-run]` — retention sweep: anonymizes entity rows older
  than `--older-than` (based on `createdAt`) AND scrubs audit rows older than the configured retention
  (`itk_dev_entity.audit.retention`, default `P1Y`, with per-entity overrides). Idempotent.

The mechanism is law-neutral; the same machinery applies to GDPR, CCPA, LGPD, PIPEDA, etc.

## Configuration

Everything is optional — see the reference table below for defaults.

```yaml
# config/packages/itk_dev_entity.yaml
itk_dev_entity:
  user_class: App\Entity\User # required when audit or blameable is enabled
  # entity_paths: ['%kernel.project_dir%/src/Entity']   # default
  # audit:
  #     enabled: false                         # default — flip to true to register entities with dh_auditor
  #     retention: P1Y                         # default (used by privacy:anonymize-stale)
  #     retention_overrides:
  #         App\Entity\FinancialTransaction: P5Y
  # soft_delete:
  #     enabled: false                         # default — opt in per entity, then flip this to true
  # archivable:
  #     enabled: false                         # default — opt in per entity, then flip this to true
  # timestampable:
  #     enabled: false                         # default — opt in per entity, then flip this to true
  # blameable:
  #     enabled: false                         # default — opt in per entity, then flip this to true
  # anonymization:
  #     enabled: false                         # default — opt in per entity (trait + #[Anonymize]), then flip this to true
```

### Configuration reference

| Key                         | Type                     | Default                               | Notes                                                                                                                                                                                                                                                                                     |
| --------------------------- | ------------------------ | ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `user_class`                | string (FQCN)            | `null`                                | Concrete `UserInterface` implementation. Required when `audit.enabled` or `blameable.enabled` is true; otherwise optional. When set, wired via Doctrine `resolve_target_entities` and used by `privacy:anonymize`.                                                                        |
| `entity_paths`              | list&lt;string&gt;       | `['%kernel.project_dir%/src/Entity']` | Directories scanned for `AbstractITKDevEntity` subclasses (audit + anonymization auto-discovery).                                                                                                                                                                                         |
| `audit.enabled`             | bool                     | `false`                               | Register discovered entities with damienharper/auditor-bundle.                                                                                                                                                                                                                            |
| `audit.retention`           | ISO-8601 duration        | `P1Y`                                 | Default retention past which audit rows are scrubbed by `privacy:anonymize-stale`.                                                                                                                                                                                                        |
| `audit.retention_overrides` | map&lt;FQCN, ISO8601&gt; | `[]`                                  | Per-entity overrides.                                                                                                                                                                                                                                                                     |
| `soft_delete.enabled`       | bool                     | `false`                               | Register `SoftDeleteListener` (intercepts `remove()`) and the `soft_delete` Doctrine filter. Entities still opt in by implementing `SoftDeletableInterface` and using `SoftDeletableTrait`.                                                                                               |
| `archivable.enabled`        | bool                     | `false`                               | Register the `archivable` Doctrine filter (registered disabled; toggle per-request). Entities still opt in by implementing `ArchivableInterface` and using `ArchivableTrait`.                                                                                                             |
| `timestampable.enabled`     | bool                     | `false`                               | Register `TimestampableListener` (sets `createdAt`/`updatedAt`). Entities still opt in by implementing `TimestampableInterface` and using `TimestampableTrait`.                                                                                                                           |
| `blameable.enabled`         | bool                     | `false`                               | Register `BlameableListener` (sets `createdBy`/`modifiedBy` from the security token). Entities still opt in by implementing `BlameableInterface` and using `BlameableTrait`.                                                                                                              |
| `anonymization.enabled`     | bool                     | `false`                               | Discover `#[Anonymize]` property attributes, register privacy services, and expose the `privacy:anonymize` and `privacy:anonymize-stale` commands. Entities still opt in by implementing `AnonymizationStatusInterface`, using `AnonymizationStatusTrait`, and annotating PII properties. |

If you set `user_class`, the class extends `AbstractITKDevEntity` and implements
`Symfony\Component\Security\Core\User\UserInterface`. Add the opt-in traits/interfaces for the features you want
(timestamps, blame, soft-delete, archivable). Bundle takes care of the rest.

## Running the bundle's own tests

```bash
cd packages/entity-bundle
composer install
vendor/bin/phpunit
```
