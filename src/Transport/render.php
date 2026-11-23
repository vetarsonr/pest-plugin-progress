<?php

declare(strict_types=1);

use Vendor\PestProgress\Collector\RunState;
use Vendor\PestProgress\Render\Renderer;
use Vendor\PestProgress\Transport\EventLog;

if (! isset($argv[4])) {
    exit(1);
}

[, $directory, $live, $color, $started] = $argv;
// A display-only child must not run the host project's Composer autoload.files.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Vendor\\PestProgress\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__.'/../'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, SIG_IGN);
}
$log = new EventLog($directory);
$state = new RunState(keepFailures: false);
$renderer = Renderer::make($live === '1', $color === '1', (float) $started);
do {
    $log->read($state);
    $renderer->update($state);
    $read = [STDIN];
    $write = $except = null;
    $ready = stream_select($read, $write, $except, 0, 100000);
} while ($ready !== false && ($ready === 0 || fread(STDIN, 1) !== ''));

$log->read($state);
$renderer->update($state, true);
