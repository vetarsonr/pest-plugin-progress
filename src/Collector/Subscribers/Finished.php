<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class Finished extends RecorderSubscriber implements Events\FinishedSubscriber
{
    public function notify(Events\Finished $event): void
    {
        $this->recorder->finish($event->test());
    }
}
