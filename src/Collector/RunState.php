<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector;

final class RunState
{
    public ?int $total = null;

    public int $completed = 0;

    public bool $aborted = false;

    /** @var array<string, true> Files awaiting a result when ParaTest can stop scheduling on failure. */
    public array $unreportedFiles = [];

    /** @var array<string, true> */
    private array $discoveryErrors = [];

    /** @var array<string, int> */
    public array $counts;

    /** @var list<Result> */
    public array $failures = [];

    public function __construct(private readonly bool $keepFailures = true)
    {
        $this->counts = array_fill_keys(array_column(Status::cases(), 'value'), 0);
    }

    public function record(Result $result): void
    {
        unset($this->unreportedFiles[$result->file]);
        // The parent and its workers can discover the same invalid provider independently.
        if ($result->discoveryId !== null) {
            // PHPUnit may append a different stack trace in each process.
            $key = $result->discoveryId."\0".explode("\n", $result->message, 2)[0];
            if (isset($this->discoveryErrors[$key])) {
                return;
            }
            $this->discoveryErrors[$key] = true;
        }
        $this->counts[$result->status->value]++;
        if (! $result->external) {
            $this->completed++;
        }
        if ($this->keepFailures && $result->status->isFailure()) {
            $this->failures[] = $result;
        }
    }

    public function discoveryErrorCount(): int
    {
        return count($this->discoveryErrors);
    }
}
