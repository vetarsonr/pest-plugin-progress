<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Transport;

use Vendor\PestProgress\Options;

final class RendererProcess
{
    /** @param resource $process
     * @param  resource  $input
     */
    private function __construct(private $process, private $input) {}

    public static function start(EventLog $log, Options $options, float $started): ?self
    {
        if (! function_exists('proc_open') || PHP_OS_FAMILY === 'Windows') {
            return null;
        }
        $command = [PHP_BINARY, __DIR__.'/render.php', $log->directory, (string) (int) $options->live,
            (string) (int) $options->color, (string) $started];
        // proc_open cannot cast Symfony's filtered STDERR stream to an OS descriptor.
        $error = @fopen('php://stderr', 'wb');
        if ($error === false) {
            return null;
        }
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => $error, 2 => $error], $pipes);
        fclose($error);

        return is_resource($process) ? new self($process, $pipes[0]) : null;
    }

    public function finish(): bool
    {
        // EOF wakes the renderer immediately, including when the parent dies.
        fclose($this->input);

        return proc_close($this->process) === 0;
    }
}
