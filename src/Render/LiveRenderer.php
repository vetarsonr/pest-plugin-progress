<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Render;

use Vendor\PestProgress\Collector\RunState;
use Vendor\PestProgress\Collector\Status;

final class LiveRenderer extends Renderer
{
    protected function draw(RunState $state, float $elapsed): void
    {
        $lines = [sprintf(' %d / %s%s   %.1fs', $state->completed, $state->total ?? '?', $this->percent($state), $elapsed)];
        foreach (Status::cases() as $status) {
            $count = $state->counts[$status->value];
            $line = sprintf(' %-12s %5d', $status->name.':', $count);
            if ($this->color && ($count > 0 || $status === Status::Passed)) {
                $color = match ($status) {
                    Status::Passed => 32,
                    Status::Failed, Status::Errors => 31,
                    default => 33,
                };
                $line = "\033[".$color.'m'.$line."\033[0m";
            }
            $lines[] = $line;
        }
        $move = $this->drawn ? "\033[".count($lines).'F' : '';
        fwrite($this->stream, $move."\033[2K".implode("\n\033[2K", $lines)."\n");
    }
}
