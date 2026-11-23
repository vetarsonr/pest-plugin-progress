<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use NunoMaduro\Collision\Adapters\Phpunit\Style;
use Pest\Exceptions\NoDirtyTestsFound;
use Pest\KernelDump;
use Pest\Panic;
use Pest\Plugins\Parallel\Paratest\ResultPrinter;
use Pest\Plugins\Parallel\Support\CompactPrinter;
use php_user_filter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Vendor\PestProgress\Run;

/**
 * Both native printers use Symfony's console stream. Select the originating
 * printer operation so identical text from a test, coverage or a recap survives.
 */
final class PrinterFilter extends php_user_filter
{
    private const NAME = 'pest-progress.printer';

    /** @return resource|false */
    public static function attach(Run|DiscoveryErrors $context, bool $stderr = false)
    {
        if (! in_array(self::NAME, stream_get_filters(), true)) {
            stream_filter_register(self::NAME, self::class);
        }

        $output = new ConsoleOutput;
        $output = $stderr ? $output->getErrorOutput() : $output;

        return $output instanceof StreamOutput ? stream_filter_append($output->getStream(), self::NAME, STREAM_FILTER_WRITE, $context) : false;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $context = $this->params;
        assert($context instanceof Run || $context instanceof DiscoveryErrors);
        $run = $context instanceof Run ? $context : null;
        $discovery = $context instanceof Run ? $context->discovery : $context;
        $mute = false;
        $native = false;
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = $frame['class'] ?? '';
            $method = $frame['function'];
            if ($class === Style::class) {
                $native = true;
                $mute = $mute || $method === 'writeCurrentTestCaseSummary'
                    || ($method === 'writeErrorsSummary' && ! $discovery->showDetails);
            } elseif ($class === CompactPrinter::class) {
                $native = true;
                if ($method === 'descriptionItem') {
                    $run?->parallelFeedback();
                    $mute = true;
                }
            } elseif ($class === ResultPrinter::class && $method === 'printResults') {
                $run?->finishCounter();
                $native = true;
            } elseif ($class === NoDirtyTestsFound::class && $method === 'render') {
                $run?->emptySuite();
                $native = true;
                break;
            } elseif (($class === Panic::class && $method === 'with')
                || ($class === Application::class && $method === 'renderThrowable' && $run?->isParallel())) {
                if ($discovery->capture($class, $method)) {
                    $run?->finishCounter();
                    $mute = ! $discovery->showDetails;
                }
                $native = true;
            } elseif (($frame['object'] ?? null) instanceof KernelDump && $method === 'flush') {
                if ($discovery->kernelDump($frame['object'])) {
                    $run?->finishCounter();
                    $mute = ! $discovery->showDetails;
                }
                $native = true;
            }
        }
        while ($bucket = stream_bucket_make_writeable($in)) {
            /** @var string $payload PHP exposes the stream bucket's data as an untyped property before 8.4. */
            $payload = $bucket->data;
            $consumed += strlen($payload);
            if (! $mute) {
                // Older parallel printers force colors, even with --colors=never.
                if ($native && ! $run?->options->color && is_resource($this->stream)) {
                    $data = (string) preg_replace('/\x1b\[[0-?]*[ -\/]*[@-~]/', '', $payload);
                    $bucket = stream_bucket_new($this->stream, $data);
                }
                stream_bucket_append($out, $bucket);
            }
        }

        return PSFS_PASS_ON;
    }
}
