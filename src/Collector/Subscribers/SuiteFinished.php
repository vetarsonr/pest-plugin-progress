<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\TestSuite as Events;

final class SuiteFinished extends RecorderSubscriber implements Events\FinishedSubscriber
{
    public function notify(Events\Finished $event): void
    {
        $this->recorder->flush();
    }
}
