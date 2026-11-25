<?php

declare(strict_types=1);

namespace Vendor\PestProgress;

use Pest\TestSuite;

use function Pest\testDirectory;

final class Options
{
    // These runner options consume the next token, even when it looks like a plugin switch.
    private const OPTIONS_WITH_VALUE = [
        '-c', '-d', '-m', '--configuration', '--bootstrap', '--include-path', '--extension',
        '--test-directory', '--test-suffix', '--test-files-file', '--test-id-filter-file', '--run-test-id',
        '--filter', '--exclude-filter', '--group', '--exclude-group', '--testsuite', '--exclude-testsuite',
        '--covers', '--uses', '--requires-php-extension', '--atleast-version', '--list-tests-xml',
        '--cache-directory', '--cache-result-file', '--generate-baseline', '--use-baseline',
        '--log-events-text', '--log-events-verbose-text', '--log-junit', '--log-otr', '--log-teamcity',
        '--testdox-html', '--testdox-text', '--coverage-cache', '--coverage-filter', '--coverage-html',
        '--coverage-clover', '--coverage-cobertura', '--coverage-jsonl', '--coverage-openclover', '--coverage-php', '--coverage-xml',
        '--order-by', '--random-order-seed', '--repeat', '--columns', '--diff-context', '--default-time-limit', '--timeout',
        '--processes', '--max-batch-size', '--passthru-php', '--runner', '--tmp-dir',
    ];

    // These options can leave files or test cases out of the loaded suite.
    private const SELECTION_OPTIONS = [
        '--filter', '--exclude-filter', '--group', '--exclude-group', '--testsuite', '--exclude-testsuite', '--all',
        '--covers', '--uses', '--requires-php-extension', '--test-suffix', '--test-files-file', '--test-id-filter-file',
        '--run-test-id', '--dirty', '--shard', '--tia', '--todo', '--todos', '--notes', '--issue', '--pr', '--pull-request', '--flaky',
    ];

    private function __construct(
        public readonly bool $enabled,
        public readonly ?string $file,
        public readonly int $verbosity,
        public readonly bool $showDetails,
        public readonly bool $live,
        public readonly bool $color,
        public readonly bool $parallel,
        public readonly bool $retry,
        public readonly ?string $configurationFile,
        public readonly bool $restrictedSelection,
    ) {}

    /**
     * Pest consumes options such as --teamcity before invoking plugins.
     *
     * @param  array<int, string>  $arguments
     * @param  array<int, string>  $original
     */
    public static function parse(array &$arguments, array $original, bool $activeWorker = false): self
    {
        $file = getenv('PEST_FAILURES_FILE');
        if ($file === false || $file === '') {
            $file = TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR.testDirectory('pest-failures.txt');
        } elseif ($file === '0') {
            $file = null;
        }
        $verbosity = self::level(getenv('PEST_FAILURES_VERBOSITY'));
        $showDetails = getenv('PEST_SHOW_DETAILS') === '1';
        $original = array_values($original);
        $arguments = array_values($arguments);
        $restrictedSelection = false;
        $originalFlags = self::flags($original, $restrictedSelection);
        $argumentFlags = self::flags($arguments);
        $flags = array_merge($originalFlags, $argumentFlags);
        $enabled = $activeWorker || getenv('PEST_PROGRESS') === '1' || in_array('--progress', $argumentFlags, true);
        foreach ($flags as $flag) {
            if (str_starts_with($flag, '--list-') || in_array($flag, [
                '--teamcity', '--version', '-V', '--help', '-h', '--no-progress-counter', '--init',
            ], true)) {
                $enabled = false;
                break;
            }
        }

        $forward = [];
        for ($i = 0, $count = count($arguments); $i < $count; $i++) {
            $argument = $arguments[$i];
            if (! isset($argumentFlags[$i])) {
                $forward[] = $argument;
            } elseif (str_starts_with($argument, '--failures-file=')) {
                $path = substr($argument, strlen('--failures-file='));
                $file = $path === '' ? null : $path;
            } elseif ($argument === '--no-failures-file') {
                $file = null;
            } elseif (str_starts_with($argument, '--failures-verbosity=')) {
                $verbosity = self::level(substr($argument, strlen('--failures-verbosity=')));
            } elseif ($argument === '--failures-verbosity') {
                $next = $arguments[$i + 1] ?? null;
                $verbosity = self::level($next !== null && ! str_starts_with($next, '-') ? $arguments[++$i] : '');
            } elseif ($argument === '--failures-verbose') {
                $verbosity = 2;
            } elseif ($argument === '--show-failure-details') {
                $showDetails = true;
            } elseif ($argument === '--hide-failure-details') {
                $showDetails = false;
            } elseif (! in_array($argument, ['--progress', '--no-progress-counter'], true)) {
                $forward[] = $argument;
            }
        }
        $arguments = $forward;

        $ci = in_array('--ci', $flags, true) || ! in_array(strtolower((string) getenv('CI')), ['', '0', 'false'], true);
        $live = PHP_OS_FAMILY !== 'Windows' && ! $ci && stream_isatty(STDERR)
            && getenv('TERM') !== 'dumb';
        $never = in_array('--colors=never', $flags, true);
        $configuration = null;
        foreach ($originalFlags as $i => $argument) {
            if ($argument === '--colors' && ($original[$i + 1] ?? '') === 'never') {
                $never = true;
            }
            if ($argument === '-c' || $argument === '--configuration') {
                $configuration = $original[$i + 1] ?? null;
            } elseif (preg_match('/^(?:--configuration=|-c=?)(.+)$/sD', $argument, $match)) {
                $configuration = $match[1];
            }
        }
        $configurationFile = null;
        if ($configuration !== null && $configuration !== '' && ($absolute = realpath($configuration)) !== false) {
            $root = TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR;
            $configurationFile = str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : $absolute;
        }

        return new self($enabled, $file, $verbosity, $showDetails, $live, $live && ! $never && getenv('NO_COLOR') === false,
            in_array('--parallel', $flags, true) || in_array('-p', $flags, true), in_array('--retry', $flags, true), $configurationFile, $restrictedSelection);
    }

    /**
     * Preserve literal option values and positional arguments after "--".
     *
     * @param  list<string>  $arguments
     * @return array<int, string> switches indexed by their position in argv
     */
    private static function flags(array $arguments, bool &$restrictedSelection = false): array
    {
        $flags = [];
        for ($i = 0, $count = count($arguments); $i < $count; $i++) {
            $argument = $arguments[$i];
            if ($argument === '--') {
                $restrictedSelection = $restrictedSelection || $i + 1 < $count;
                break;
            }
            if (str_starts_with($argument, '-')) {
                $flags[$i] = $argument;
                $restrictedSelection = $restrictedSelection || in_array(explode('=', $argument, 2)[0], self::SELECTION_OPTIONS, true);
            } elseif ($i !== 0) {
                $restrictedSelection = true;
            }
            if (in_array($argument, self::OPTIONS_WITH_VALUE, true)
                || ($argument === '--failures-verbosity' && isset($arguments[$i + 1]) && ! str_starts_with($arguments[$i + 1], '-'))) {
                $i++;
            }
        }

        return $flags;
    }

    private static function level(string|false $value): int
    {
        return $value === '1' ? 1 : 2;
    }
}
