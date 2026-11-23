<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestSuite as Events;
use Vendor\PestProgress\Run;

final class SuiteFiltered implements Events\FilteredSubscriber
{
    public function __construct(private readonly Run $run) {}

    public function notify(Events\Filtered $event): void
    {
        $this->run->parallelSuite($event->testSuite());
    }
}
