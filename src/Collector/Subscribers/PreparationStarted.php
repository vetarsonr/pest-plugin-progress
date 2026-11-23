<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class PreparationStarted extends RecorderSubscriber implements Events\PreparationStartedSubscriber
{
    public function notify(Events\PreparationStarted $event): void
    {
        $this->recorder->flush($event->test()->id());
    }
}
