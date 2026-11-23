<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class Failed extends RecorderSubscriber implements Events\FailedSubscriber
{
    public function notify(Events\Failed $event): void
    {
        $this->recorder->observe($event->test(), Status::Failed, $event->throwable());
    }
}
