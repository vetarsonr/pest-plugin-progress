<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestRunner as Events;
use Vendor\PestProgress\Run;

final class ExecutionStarted implements Events\ExecutionStartedSubscriber
{
    public function __construct(private readonly Run $run) {}

    public function notify(Events\ExecutionStarted $event): void
    {
        $this->run->startExecution($event->testSuite()->count());
    }
}
