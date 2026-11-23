<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use PHPUnit\Event\Application as Events;
use Vendor\PestProgress\Run;

final class ApplicationStarted implements Events\StartedSubscriber
{
    public function __construct(private readonly Run $run) {}

    public function notify(Events\Started $event): void
    {
        $this->run->sequentialApplication();
    }
}
