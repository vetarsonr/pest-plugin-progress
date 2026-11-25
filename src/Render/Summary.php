<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Render;

use Pest\TestSuite;
use PHPUnit\Event\Code\TestCollection;
use Vendor\PestProgress\Collector\RunState;
use Vendor\PestProgress\Failures\FailureList;
use Vendor\PestProgress\Failures\FailuresFileWriter;
use Vendor\PestProgress\Failures\TextReport;
use Vendor\PestProgress\Options;

final class Summary
{
    public static function print(RunState $state, Options $options, string $cwd, bool $interrupted, bool $stoppedEarly, ?TestCollection $discoveredTests = null): void
    {
        $runNote = '';
        if ($interrupted) {
            $runNote = sprintf('Interrupted: %d / %s', $state->completed, $state->total ?? '?');
        } elseif ($state->total !== null && $state->completed < $state->total) {
            $runNote = $stoppedEarly ? sprintf('Stopped early: %d / %d', $state->completed, $state->total)
                : sprintf('Recorded %d of %d discovered tests.', $state->completed, $state->total);
            $discoveryErrors = $state->discoveryErrorCount();
            if ($discoveryErrors > 0) {
                $runNote .= sprintf(' %d error%s during test discovery.', $discoveryErrors, $discoveryErrors === 1 ? '' : 's');
            }
        }
        if ($runNote !== '') {
            fwrite(STDERR, $runNote."\n");
        }

        $suite = TestSuite::getInstance();
        $failures = new FailureList($state->failures, $cwd, $suite->rootPath);
        $destination = '';
        if ($options->file !== null) {
            $path = $options->file;
            if (! str_starts_with($path, '/') && ! preg_match('~^(?:[a-z]:[/\\\\]|\\\\\\\\)~i', $path)) {
                $path = $cwd.'/'.$path;
            }
            if (FailuresFileWriter::write($path, TextReport::format($failures, $runNote, $suite->testPath, $options->configurationFile, $discoveredTests))) {
                $destination = ' — written to '.$options->file;
            } else {
                fwrite(STDERR, sprintf("[progress] Could not update %s\n", $options->file));
            }
        }
        if ($failures->entries === []) {
            fwrite(STDERR, "No failed tests.\n");

            return;
        }
        fwrite(STDERR, sprintf("Failed tests (%d)%s\n\n", count($failures->entries), $destination));
        if ($options->verbosity === 1) {
            fwrite(STDERR, implode("\n", $failures->locations())."\n");

            return;
        }

        foreach ($failures->entries as $entry) {
            fwrite(STDERR, $entry->label()."\n");
        }
    }
}
