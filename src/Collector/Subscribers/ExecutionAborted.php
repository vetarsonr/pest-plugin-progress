<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use Closure;
use PHPUnit\Event\TestRunner as Events;

final class ExecutionAborted implements Events\ExecutionAbortedSubscriber
{
    /** @param Closure(): void $aborted */
    public function __construct(private readonly Closure $aborted) {}

    public function notify(Events\ExecutionAborted $event): void
    {
        ($this->aborted)();
    }
}
