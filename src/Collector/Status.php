<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector;

enum Status: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Errors = 'errors';
    case Skipped = 'skipped';
    case Todo = 'todo';
    case Incomplete = 'incomplete';
    case Risky = 'risky';

    public function severity(): int
    {
        return match ($this) {
            self::Passed => 0,
            self::Skipped, self::Todo => 1,
            self::Incomplete => 2,
            self::Risky => 3,
            self::Failed => 4,
            self::Errors => 5,
        };
    }

    public function isFailure(): bool
    {
        return $this === self::Failed || $this === self::Errors;
    }
}
