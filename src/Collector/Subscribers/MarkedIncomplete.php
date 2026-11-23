<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class MarkedIncomplete extends RecorderSubscriber implements Events\MarkedIncompleteSubscriber
{
    public function notify(Events\MarkedIncomplete $event): void
    {
        $this->recorder->observe($event->test(), Status::Incomplete);
    }
}
