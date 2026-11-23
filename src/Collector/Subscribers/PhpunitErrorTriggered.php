<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Test as Events;

final class PhpunitErrorTriggered extends RecorderSubscriber implements Events\PhpunitErrorTriggeredSubscriber
{
    public function notify(Events\PhpunitErrorTriggered $event): void
    {
        $this->recorder->phpunitError($event->test(), $event->message());
    }
}
