<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use Closure;
use ParaTest\WrapperRunner\WorkerCrashedException;
use Pest\KernelDump;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\TestSuiteLoader;
use ReflectionProperty;
use Throwable;
use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\Status;

final class DiscoveryErrors
{
    private bool $reported = false;

    /** @param Closure(Result): void $record */
    public function __construct(private readonly Closure $record, public readonly bool $showDetails) {}

    // Older PHPUnit versions exit with a crash message that Pest's KernelDump prints.
    public function kernelDump(KernelDump $dump): bool
    {
        if (! $this->reported) {
            $buffer = (new ReflectionProperty($dump, 'buffer'))->getValue($dump);
            if (is_string($buffer) && preg_match('/^Location: (.+):(\d+)\r?$/m', $buffer, $match)) {
                $this->reported = true;
                // Pest 2 has already unwound the loader. Its preceding frame belongs to the test that included the helper.
                $testFile = $match[1];
                if (preg_match('~^#\d+ ([^\r\n]+)\(\d+\): [^\n]*\n#\d+ [^\r\n]*[/\\\\]TestSuiteLoader\.php\(\d+\): include(?:_once)?\(~m', $buffer, $frame)) {
                    $testFile = $frame[1];
                }
                ($this->record)(new Result(Status::Errors, $match[1], (int) $match[2], 'PHPUnit',
                    $buffer, true, testFile: $testFile));
            }
        }

        return $this->reported;
    }

    public function capture(string $class, string $method): bool
    {
        if ($this->reported) {
            return true;
        }
        // Pest 4/5 routes loader exceptions to Panic::with() before exiting.
        $error = null;
        $testFile = null;
        foreach (debug_backtrace(0) as $frame) {
            if (($frame['class'] ?? '') === $class && $frame['function'] === $method) {
                $error ??= $frame['args'][0] ?? null;
            }
            if (($frame['class'] ?? '') === TestSuiteLoader::class && $frame['function'] === 'load' && is_string($frame['args'][0] ?? null)) {
                $testFile = $frame['args'][0];
                break;
            }
        }
        // A worker has already reported its own hook or fatal error to the log.
        if ($error instanceof WorkerCrashedException) {
            return true;
        }
        if (! $error instanceof Throwable || ! $this->isSuiteException($error)) {
            return false;
        }
        $this->reported = true;
        $trace = $error->getTraceAsString();
        if ($error->getPrevious() instanceof Throwable) {
            $trace .= "\nCaused by\n".$error->getPrevious();
        }
        ($this->record)(new Result(Status::Errors, $error->getFile(), $error->getLine(), $error::class,
            $error->getMessage(), true, $trace, testFile: $testFile));

        return true;
    }

    private function isSuiteException(Throwable $error): bool
    {
        foreach ($error->getTrace() as $frame) {
            $class = $frame['class'] ?? '';
            if ($class === TestSuiteLoader::class || (is_a($class, TestCase::class, true)
                && in_array($frame['function'], ['setUpBeforeClass', 'tearDownAfterClass'], true))) {
                return true;
            }
        }

        return false;
    }

    public function fatal(): void
    {
        $error = error_get_last();
        if (! $this->reported && $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            ($this->record)(new Result(Status::Errors, $error['file'], $error['line'], 'PHP', $error['message'], true));
        }
    }
}
