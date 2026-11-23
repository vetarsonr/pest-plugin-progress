<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector\Subscribers;

use Vendor\PestProgress\Collector\ResultRecorder;

abstract class RecorderSubscriber
{
    public function __construct(protected readonly ResultRecorder $recorder) {}
}
