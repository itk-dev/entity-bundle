# Security policy

## Supported versions

Security fixes are issued for the latest minor release on the `main` branch.
Older versions are not maintained — upgrade to receive fixes.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security problems.

Report privately via GitHub's security advisory form:
<https://github.com/itk-dev/entity-bundle/security/advisories/new>

Include:

- A description of the issue and its impact.
- Steps to reproduce (a minimal failing case is ideal).
- Affected versions, if known.
- Your preferred contact for follow-up.

## Scope

This bundle handles personally identifiable data through its anonymization
and audit features. Reports that materially affect the confidentiality or
integrity of that data are in scope, including (non-exhaustive):

- Anonymization strategies that fail to anonymize as documented.
- Audit log entries that retain personal data after scrubbing.
- SQL filter bypasses that expose soft-deleted or archived rows.
- Dependency vulnerabilities surfaced by the project's `composer audit` job.

Misuse by a host application (e.g. passing untrusted YAML into bundle
configuration, exposing the privacy console commands to unauthenticated
users) is out of scope; the bundle treats its configuration and CLI
invocations as trusted.
