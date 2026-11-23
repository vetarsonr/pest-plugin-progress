<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Render;

use Vendor\PestProgress\Collector\RunState;

abstract class Renderer
{
    protected bool $drawn = false;

    /** @var resource */
    protected $stream;

    private float $lastDraw;

    private int $lastCount = -1;

    private ?int $lastTotal = null;

    private int $lastErrors = -1;

    public function __construct(
        protected readonly float $started,
        protected readonly bool $color,
        private readonly float $interval,
    ) {
        $this->stream = fopen('php://stderr', 'wb') ?: STDERR;
        $this->lastDraw = $this instanceof PlainRenderer ? $started : 0.0;
    }

    public static function make(bool $live, bool $color, float $started): self
    {
        return $live ? new LiveRenderer($started, $color, 0.1) : new PlainRenderer($started, false, 10.0);
    }

    public function update(RunState $state, bool $final = false): void
    {
        if ($this->lastCount === $state->completed && $this->lastTotal === $state->total && $this->lastErrors === $state->counts['errors']) {
            return;
        }
        $now = hrtime(true) / 1e9;
        if (! $final && ($now - $this->lastDraw < $this->interval || PHP_OS_FAMILY === 'Windows')) {
            return;
        }
        $this->draw($state, $now - $this->started);
        $this->drawn = true;
        $this->lastCount = $state->completed;
        $this->lastTotal = $state->total;
        $this->lastErrors = $state->counts['errors'];
        $this->lastDraw = $now;
    }

    protected function percent(RunState $state): string
    {
        return $state->total === null ? '' : sprintf(' (%d%%)', $state->total === 0 ? 0 : intdiv(100 * $state->completed, $state->total));
    }

    abstract protected function draw(RunState $state, float $elapsed): void;
}
