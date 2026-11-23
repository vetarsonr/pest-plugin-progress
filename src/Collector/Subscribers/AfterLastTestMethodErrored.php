<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class AfterLastTestMethodErrored extends RecorderSubscriber implements Events\AfterLastTestMethodErroredSubscriber
{
    public function notify(Events\AfterLastTestMethodErrored $event): void
    {
        $this->recorder->hook($event->testClassName(), $event->calledMethod()->methodName(), $event->throwable());
    }
}
