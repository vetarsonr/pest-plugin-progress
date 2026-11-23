<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class Passed extends RecorderSubscriber implements Events\PassedSubscriber
{
    public function notify(Events\Passed $event): void
    {
        $this->recorder->observe($event->test(), Status::Passed);
    }
}
