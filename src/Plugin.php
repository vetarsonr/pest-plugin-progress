<?php

declare(strict_types=1);

namespace Vendor\PestProgress;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Parallel;
use Vendor\PestProgress\Collector\ResultRecorder;
use Vendor\PestProgress\Collector\Subscribers;
use Vendor\PestProgress\Compat\DiscoveryErrors;
use Vendor\PestProgress\Compat\PHPUnitEvents;
use Vendor\PestProgress\Compat\PrinterFilter;
use Vendor\PestProgress\Compat\Termination;
use Vendor\PestProgress\Transport\EventLog;
use Vendor\PestProgress\Transport\RunDirectory;

final class Plugin implements HandlesArguments, Termination
{
    private ?Run $run = null;

    private ?ResultRecorder $worker = null;

    private ?DiscoveryErrors $workerErrors = null;

    /** @var resource|false|null */
    private $workerFilter;

    /** @param array<int, string> $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        /** @var array<int, string> $original */
        $original = $_SERVER['argv'] ?? [];
        $isWorker = Parallel::isWorker();
        // ParaTest starts workers after we have consumed the parent's --progress flag.
        $log = $isWorker ? RunDirectory::workerLog() : null;
        $options = Options::parse($arguments, $original, $log instanceof EventLog);
        if (! $options->enabled) {
            return $arguments;
        }
        if ($isWorker) {
            if ($log instanceof EventLog) {
                $this->worker = new ResultRecorder($log->append(...));
                Subscribers::register($this->worker, $log->aborted(...));
                $this->workerErrors = new DiscoveryErrors($log->append(...), $options->showDetails);
                $this->workerFilter = PrinterFilter::attach($this->workerErrors);
            }

            return $arguments;
        }
        RunDirectory::prune();
        $directory = $options->parallel ? RunDirectory::create($options->showDetails) : null;
        if ($options->parallel && ! $directory instanceof RunDirectory) {
            fwrite(STDERR, "[progress] Could not create the parallel event log. Using the Pest printer.\n");

            return $arguments;
        }
        $run = $this->run = new Run($options, $directory);
        Subscribers::register($run->recorder, $run->aborted(...));
        PHPUnitEvents::register(
            new Subscribers\ApplicationStarted($run),
            new Subscribers\SuiteLoaded($run),
            new Subscribers\SuiteFiltered($run),
            new Subscribers\ExecutionStarted($run),
            new Subscribers\ExecutionFinished($run),
        );
        // ParaTest's ResultPrinter counts passes while consuming native progress.
        if ($options->parallel) {
            $arguments = array_values(array_diff($arguments, ['--no-progress']));
        } elseif (! in_array('--no-progress', $arguments, true)) {
            $end = array_search('--', $arguments, true);
            array_splice($arguments, $end === false ? count($arguments) : $end, 0, ['--no-progress']);
        }

        return $arguments;
    }

    public function terminate(): void
    {
        $this->worker?->flush();
        $this->workerErrors?->fatal();
        if (is_resource($this->workerFilter)) {
            stream_filter_remove($this->workerFilter);
        }
        $this->run?->terminate();
    }

    public function shutdown(): void
    {
        $this->terminate();
    }
}
