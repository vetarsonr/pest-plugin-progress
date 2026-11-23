<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use Closure;

final class InterruptHandler
{
    public bool $interrupted = false;

    private ?Closure $handler = null;

    /** @var int|callable */
    private $previous = 0;

    private bool $async = false;

    /** @param Closure(): void $finish */
    public function __construct(private readonly Closure $finish) {}

    public function install(): void
    {
        if (! function_exists('pcntl_signal_get_handler')) {
            return;
        }
        $previous = pcntl_signal_get_handler(SIGINT);
        if ($previous === $this->handler || $previous === SIG_IGN) {
            return;
        }
        $this->previous = $previous;
        $this->async = pcntl_async_signals(true);
        $pid = getmypid();
        if ($pid === false) {
            return;
        }
        $this->handler = function (int $signal, mixed $info = null) use ($previous, $pid): void {
            if (getmypid() !== $pid) {
                return;
            }
            $this->interrupted = true;
            if (is_callable($previous)) {
                // pcntl delivers both arguments; existing handlers can accept either arity.
                (new \ReflectionFunction(Closure::fromCallable($previous)))->invoke($signal, $info);

                return;
            }
            ($this->finish)();
            pcntl_signal($signal, SIG_DFL);
            if (function_exists('posix_kill')) {
                posix_kill($pid, $signal);
            } else {
                exit(128 + $signal);
            }
        };
        pcntl_signal(SIGINT, $this->handler);
    }

    public function restore(): void
    {
        if ($this->handler instanceof Closure && pcntl_signal_get_handler(SIGINT) === $this->handler) {
            pcntl_signal(SIGINT, $this->previous);
            pcntl_async_signals($this->async);
        }
    }
}
