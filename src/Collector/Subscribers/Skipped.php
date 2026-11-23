<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class Skipped extends RecorderSubscriber implements Events\SkippedSubscriber
{
    public function notify(Events\Skipped $event): void
    {
        $this->recorder->observe($event->test(), $event->message() === '__TODO__' ? Status::Todo : Status::Skipped);
    }
}
