<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class Errored extends RecorderSubscriber implements Events\ErroredSubscriber
{
    public function notify(Events\Errored $event): void
    {
        $this->recorder->observe($event->test(), Status::Errors, $event->throwable());
    }
}
