<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestSuite as Events;
use Vendor\PestProgress\Collector\Status;

final class SuiteSkipped extends RecorderSubscriber implements Events\SkippedSubscriber
{
    public function notify(Events\Skipped $event): void
    {
        foreach ($event->testSuite()->tests() as $test) {
            $this->recorder->observe($test, Status::Skipped);
            $this->recorder->finish($test);
        }
    }
}
