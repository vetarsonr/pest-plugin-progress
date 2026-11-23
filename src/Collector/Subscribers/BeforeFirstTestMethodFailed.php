<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class BeforeFirstTestMethodFailed extends RecorderSubscriber implements Events\BeforeFirstTestMethodFailedSubscriber
{
    public function notify(Events\BeforeFirstTestMethodFailed $event): void
    {
        $this->recorder->hook($event->testClassName(), $event->calledMethod()->methodName(), $event->throwable());
    }
}
