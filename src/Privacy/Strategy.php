<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

/**
 * GDPR semantics of each strategy:
 *
 * - NullValue, Redact, Hash → anonymization. Output is unlinkable to the
 *   source value and to other rows scrubbed with the same strategy. Once
 *   applied, the resulting column is no longer personal data.
 * - Pseudonymize → pseudonymization. Output is deterministic in the source
 *   value and kernel.secret; rows that shared a cleartext value still
 *   collide post-scrubbing. The result remains personal data under GDPR
 *   Recital 26 — treat with the same access controls as the cleartext.
 */
enum Strategy: string
{
    case NullValue = 'null';
    case Redact = 'redact';
    case Hash = 'hash';
    case Pseudonymize = 'pseudonymize';
}
