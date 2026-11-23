<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector;

use Closure;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\Throwable;
use Vendor\PestProgress\Compat\TestLocation;

final class ResultRecorder
{
    /** @var array<string, Result> */
    private array $pending = [];

    private ?string $activeTest = null;

    private readonly TestLocation $location;

    /** @param Closure(Result): void $record */
    public function __construct(private readonly Closure $record)
    {
        $this->location = new TestLocation;
    }

    public function observe(Test $test, Status $status, ?Throwable $exception = null, string $message = ''): void
    {
        $id = $test->id();
        if (! isset($this->pending[$id]) || $this->pending[$id]->status->severity() < $status->severity()) {
            $this->pending[$id] = $this->location->result($test, $status, $exception, $message);
        }
    }

    public function finish(Test $test): void
    {
        $id = $test->id();
        ($this->record)($this->pending[$id] ?? $this->location->result($test, Status::Passed));
        unset($this->pending[$id]);
        $this->activeTest = null;
    }

    // PHPUnit does not emit Finished for tests skipped or errored before preparation.
    public function flush(?string $startingTest = null): void
    {
        $this->activeTest = $startingTest;
        foreach ($this->pending as $id => $result) {
            if ($id !== $startingTest) {
                ($this->record)($result);
                unset($this->pending[$id]);
            }
        }
    }

    public function phpunitError(Test $test, string $message): void
    {
        if ($test->id() === $this->activeTest) {
            $this->observe($test, Status::Errors, message: $message);

            return;
        }

        // A rejected provider has no executable test case and never emits Finished.
        ($this->record)($this->location->result($test, Status::Errors, message: $message, discovery: true));
    }

    /** @param class-string $class */
    public function hook(string $class, string $method, Throwable $exception): void
    {
        ($this->record)($this->location->hook($class, $method, $exception));
    }
}
