<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class BeforeFirstTestMethodErrored extends RecorderSubscriber implements Events\BeforeFirstTestMethodErroredSubscriber
{
    public function notify(Events\BeforeFirstTestMethodErrored $event): void
    {
        $this->recorder->hook($event->testClassName(), $event->calledMethod()->methodName(), $event->throwable());
    }
}
