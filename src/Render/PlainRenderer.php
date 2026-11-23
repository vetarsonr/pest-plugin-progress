<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Render;

use Vendor\PestProgress\Collector\RunState;

final class PlainRenderer extends Renderer
{
    protected function draw(RunState $state, float $elapsed): void
    {
        $line = sprintf('[progress] %d/%s%s', $state->completed, $state->total ?? '?', $this->percent($state));
        foreach (['failed', 'errors', 'skipped', 'passed', 'todo', 'incomplete', 'risky'] as $status) {
            $line .= sprintf(' %s=%d', $status, $state->counts[$status]);
        }
        fwrite($this->stream, $line."\n");
    }
}
