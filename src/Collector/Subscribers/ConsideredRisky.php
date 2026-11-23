<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;
use Vendor\PestProgress\Collector\Status;

final class ConsideredRisky extends RecorderSubscriber implements Events\ConsideredRiskySubscriber
{
    public function notify(Events\ConsideredRisky $event): void
    {
        $this->recorder->observe($event->test(), Status::Risky);
    }
}
