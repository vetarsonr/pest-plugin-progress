<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestSuite as Events;
use Vendor\PestProgress\Run;

final class SuiteLoaded implements Events\LoadedSubscriber
{
    public function __construct(private readonly Run $run) {}

    public function notify(Events\Loaded $event): void
    {
        $this->run->parallelSuite($event->testSuite());
    }
}
