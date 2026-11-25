<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector;

final class Result
{
    public function __construct(
        public readonly Status $status,
        public readonly string $file,
        public readonly ?int $line,
        public readonly string $name,
        public readonly string $message = '',
        public readonly bool $external = false,
        public readonly string $trace = '',
        public readonly ?string $discoveryId = null,
        public readonly ?string $testClass = null,
        public readonly ?string $testFile = null,
    ) {}
}
