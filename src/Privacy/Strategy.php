<?php

declare(strict_types=1);

namespace ITKDev\EntityBundle\Privacy;

enum Strategy: string
{
    case NullValue = 'null';
    case Redact = 'redact';
    case Hash = 'hash';
    case Pseudonymize = 'pseudonymize';
}
