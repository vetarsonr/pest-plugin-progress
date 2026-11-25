<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Failures;

use Vendor\PestProgress\Collector\Result;

final class FailureList
{
    /** @var list<FailureEntry> */
    public readonly array $entries;

    /** @param list<Result> $failures */
    public function __construct(array $failures, string $cwd, ?string $projectRoot = null)
    {
        $entries = array_map(static fn (Result $result): FailureEntry => new FailureEntry($result, $cwd, $projectRoot), $failures);
        usort($entries, static fn (FailureEntry $a, FailureEntry $b): int => strcmp($a->path, $b->path)
            ?: ($a->line <=> $b->line) ?: strcmp($a->name, $b->name) ?: strcmp($a->message, $b->message));
        $this->entries = $entries;
    }

    /** @return list<string> */
    public function locations(): array
    {
        $locations = array_map(static fn (FailureEntry $entry): string => $entry->location(), $this->entries);

        return array_values(array_unique($locations));
    }
}
