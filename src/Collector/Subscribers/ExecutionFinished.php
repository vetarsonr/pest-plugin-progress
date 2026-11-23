<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestRunner as Events;
use Vendor\PestProgress\Run;

final class ExecutionFinished implements Events\ExecutionFinishedSubscriber
{
    public function __construct(private readonly Run $run) {}

    public function notify(Events\ExecutionFinished $event): void
    {
        $this->run->finishCounter();
    }
}
