<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class AfterLastTestMethodFailed extends RecorderSubscriber implements Events\AfterLastTestMethodFailedSubscriber
{
    public function notify(Events\AfterLastTestMethodFailed $event): void
    {
        $this->recorder->hook($event->testClassName(), $event->calledMethod()->methodName(), $event->throwable());
    }
}
