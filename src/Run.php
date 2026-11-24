<?php

declare(strict_types=1);

namespace Vendor\PestProgress;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use PHPUnit\Event\TestRunner\ExecutionAbortedSubscriber;
use PHPUnit\Event\TestSuite\TestSuite;
use PHPUnit\TextUI\Configuration\Registry;
use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\ResultRecorder;
use Vendor\PestProgress\Collector\RunState;
use Vendor\PestProgress\Compat\DiscoveryErrors;
use Vendor\PestProgress\Compat\InterruptHandler;
use Vendor\PestProgress\Compat\PrinterFilter;
use Vendor\PestProgress\Compat\TestLocation;
use Vendor\PestProgress\Render\Renderer;
use Vendor\PestProgress\Render\Summary;
use Vendor\PestProgress\Transport\RendererProcess;
use Vendor\PestProgress\Transport\RunDirectory;

final class Run
{
    public readonly ResultRecorder $recorder;

    public readonly DiscoveryErrors $discovery;

    private readonly RunState $state;

    private readonly Renderer $renderer;

    private readonly InterruptHandler $signals;

    private readonly float $started;

    private readonly string $cwd;

    private ?RendererProcess $process = null;

    /** @var resource|false */
    private $filter;

    /** @var resource|false */
    private $errorFilter;

    private bool $active = false;

    private bool $counterFinished = false;

    private bool $terminated = false;

    private float $lastPoll = 0.0;

    public function __construct(public readonly Options $options, private ?RunDirectory $directory)
    {
        $this->started = hrtime(true) / 1e9;
        $this->cwd = (string) getcwd();
        $this->state = new RunState;
        $this->renderer = Renderer::make($options->live, $options->color, $this->started);
        $this->recorder = new ResultRecorder($this->record(...));
        $this->discovery = new DiscoveryErrors($this->record(...), $options->showDetails);
        $this->signals = new InterruptHandler($this->terminate(...));
        $this->filter = PrinterFilter::attach($this);
        $this->errorFilter = $this->directory instanceof RunDirectory ? PrinterFilter::attach($this, true) : false;
        $this->signals->install();
    }

    public function isParallel(): bool
    {
        return $this->directory instanceof RunDirectory;
    }

    public function aborted(): void
    {
        $this->state->aborted = true;
    }

    private function stoppedEarly(): bool
    {
        if ($this->state->aborted) {
            return true;
        }
        if ($this->isParallel()) {
            // ParaTest can drop entire queued files without aborting a suite inside a worker.
            return $this->state->unreportedFiles !== [] && $this->state->failures !== [];
        }
        if (interface_exists(ExecutionAbortedSubscriber::class) || $this->state->total === null
            || $this->state->completed >= $this->state->total) {
            return false;
        }

        // Early PHPUnit 10 has no execution-aborted event.
        $config = Registry::get();
        $counts = $this->state->counts;

        return ($counts['failed'] > 0 && ($config->stopOnFailure() || $config->stopOnDefect()))
            || ($counts['errors'] > $this->state->discoveryErrorCount() && ($config->stopOnError() || $config->stopOnDefect()))
            || ($counts['risky'] > 0 && ($config->stopOnRisky() || $config->stopOnDefect()))
            || ($counts['incomplete'] > 0 && $config->stopOnIncomplete())
            || ($counts['skipped'] + $counts['todo'] > 0 && $config->stopOnSkipped());
    }

    public function sequentialApplication(): void
    {
        // Skip formatting per-test descriptions that the counter hides.
        DefaultPrinter::compact(true);

        // Pest runs some combinations (e.g. --parallel --todo) in one process.
        $this->directory?->close();
        $this->directory = null;
    }

    public function parallelSuite(TestSuite $suite): void
    {
        if ($this->directory instanceof RunDirectory) {
            $this->active = true;
            $this->directory->log->total($suite->count());
            if (Registry::get()->stopOnFailure()) {
                $location = new TestLocation;
                $this->state->unreportedFiles = [];
                foreach ($suite->tests() as $test) {
                    $this->state->unreportedFiles[$location->file($test)] = true;
                }
            }
            $this->process ??= RendererProcess::start($this->directory->log, $this->options, $this->started);
        }
    }

    public function startExecution(int $total): void
    {
        $this->active = true;
        $this->state->total = $this->options->retry ? null : $total;
        $this->signals->install();
        $this->renderer->update($this->state);
    }

    public function emptySuite(): void
    {
        $this->active = true;
        $this->state->total = 0;
        $this->finishCounter();
    }

    private function record(Result $result): void
    {
        $this->active = true;
        if ($this->directory instanceof RunDirectory) {
            $this->directory->log->append($result);
        } else {
            $this->state->record($result);
            $this->renderer->update($this->state);
        }
    }

    public function parallelFeedback(): void
    {
        if (! $this->process instanceof RendererProcess && $this->directory instanceof RunDirectory && microtime(true) - $this->lastPoll >= 0.1) {
            $this->directory->log->read($this->state);
            $this->renderer->update($this->state);
            $this->lastPoll = microtime(true);
        }
    }

    public function finishCounter(): void
    {
        if ($this->counterFinished) {
            return;
        }
        $this->counterFinished = true;
        $this->recorder->flush();
        $drawn = $this->process?->finish();
        $this->process = null;
        $this->directory?->log->read($this->state);
        if ($this->options->retry && ! $this->signals->interrupted) {
            $this->state->total = $this->state->completed;
        }
        if ($drawn !== true) {
            $this->renderer->update($this->state, true);
        }
    }

    public function terminate(): void
    {
        if ($this->terminated) {
            return;
        }
        $this->terminated = true;
        $this->discovery->fatal();
        try {
            $summarize = $this->active || $this->signals->interrupted;
            if ($summarize) {
                $this->finishCounter();
            }
        } finally {
            $this->directory?->close();
            $this->signals->restore();
            if (is_resource($this->filter)) {
                stream_filter_remove($this->filter);
            }
            if (is_resource($this->errorFilter)) {
                stream_filter_remove($this->errorFilter);
            }
        }
        if ($summarize) {
            Summary::print($this->state, $this->options, $this->cwd, $this->signals->interrupted, $this->stoppedEarly());
        }
    }
}
