<?php

declare(strict_types=1);

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Vendor\PestProgress\Plugin;

beforeEach(function () {
    $this->temp = sys_get_temp_dir().'/progress-plugin-test-'.bin2hex(random_bytes(8));
    mkdir($this->temp, 0700);
});

afterEach(function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->temp);
});

function progressFixture(
    array $arguments,
    array $env = [],
    ?string $cwd = null,
    ?string $binary = null,
    string $testDirectory = 'tests-fixtures',
): Process {
    $root = dirname(__DIR__);
    $process = new Process([
        PHP_BINARY, $binary ?? $root.'/vendor/bin/pest', '--test-directory='.$testDirectory,
        '--configuration='.$root.'/tests-fixtures/phpunit.xml', '--colors=never', ...$arguments,
    ], $cwd ?? $root, $env + [
        'PEST_PROGRESS' => false, 'PEST_PROGRESS_RUN' => false, 'PARATEST' => false, 'TEST_TOKEN' => false,
        'PEST_FAILURES_FILE' => '0', 'PEST_FAILURES_VERBOSE' => false, 'PEST_HIDE_DETAILS' => false,
        'PEST_FAILURES_VERBOSITY' => false, 'PEST_SHOW_DETAILS' => false,
    ]);
    $process->setTimeout(40);
    $process->run();

    return $process;
}

// Give Pest a Composer plugin manifest with this package absent, without modifying vendor/.
function progressNativeBinary(string $temp): string
{
    $root = dirname(__DIR__);
    mkdir($temp.'/bin');
    $plugins = json_decode(file_get_contents($root.'/vendor/pest-plugins.json'), true, flags: JSON_THROW_ON_ERROR);
    file_put_contents($temp.'/pest-plugins.json', json_encode(array_values(array_diff($plugins, [Plugin::class])), JSON_THROW_ON_ERROR));
    file_put_contents($temp.'/native.php', '<?php $GLOBALS["_composer_bin_dir"] = __DIR__."/bin"; require '.var_export($root.'/vendor/pestphp/pest/bin/pest', true).';');

    return $temp.'/native.php';
}

function progressLocations(): string
{
    return "tests-fixtures/suite/ErrorTest.php:6\ntests-fixtures/suite/FailureTest.php:8\ntests-fixtures/suite/FailureTest.php:12\n";
}

function progressFailureLabels(): string
{
    return "tests-fixtures/suite/ErrorTest.php:6  › it reports an exception\n"
        ."tests-fixtures/suite/FailureTest.php:8  › it rejects the value with dataset \"first\"\n"
        ."tests-fixtures/suite/FailureTest.php:8  › it rejects the value with dataset \"second\"\n"
        ."tests-fixtures/suite/FailureTest.php:12  › it checks through a helper\n";
}

dataset('progress runners', [
    'sequential' => [[]],
    'two workers' => [['--parallel', '--processes=2']],
    'four workers' => [['--parallel', '--processes=4']],
]);

it('keeps native results, hides the failure blocks and records each case in a full text report with --progress', function (array $runner) {
    $file = $this->temp.'/nested/failures.txt';
    $args = [...$runner, '--compact'];
    $native = progressFixture($args, binary: progressNativeBinary($this->temp));
    $process = progressFixture([...$args, '--progress', '--failures-file='.$file]);
    $out = $process->getOutput();
    $err = $process->getErrorOutput();
    $report = file_get_contents($file);
    $divider = str_repeat('-', 72);
    expect($process->getExitCode())->toBe($native->getExitCode())->not->toBe(0)
        ->and($out)->toContain('Tests:', '10 passed', 'Duration:')->not->toContain('[progress]', 'FAILED', 'Failed asserting')
        ->and(preg_match('/^\s*[.!⨯st✓]+\s*$/mu', $out))->toBe(0)
        ->and($out.$err)->not->toContain("\033", 'Failed asserting', 'Fixture exception', 'Stack trace')
        ->and($err)->toContain('[progress] 19/19 (100%) failed=3 errors=1 skipped=2 passed=10 todo=1 incomplete=1 risky=1')
        ->and(substr_count($err, '[progress]'))->toBe(1)
        ->and($err)->toEndWith("Failed tests (4) — written to $file\n\n".progressFailureLabels())
        ->and(substr_count($report, "\n".$divider."\n"))->toBe(4)
        ->and($report)->toStartWith("Failed tests (4)\n\nLocations\n".progressLocations()."\n".$divider."\n[1/4] ERROR - it reports an exception\n")
        ->toContain('[2/4] FAILURE - it rejects the value with dataset "first"', '[3/4] FAILURE - it rejects the value with dataset "second"',
            '[4/4] FAILURE - it checks through a helper', "Fixture exception\n  The record is missing its identifier.", "\nStack trace\n",
            'expect($value)->toBe(0);', "expect('helper')->toBe('expected');", '--- Expected', '+++ Actual', 'tests-fixtures/helper.php:7')
        ->not->toContain("\033");
})->with('progress runners');

it('varies terminal detail while preserving the same full report at every level', function (array $runner) {
    $file = $this->temp.'/report.txt';
    $args = ['--progress', ...$runner, '--failures-file='.$file, '--ci'];
    $compact = progressFixture([...$args, '--failures-verbosity', '1']);
    expect($compact->getErrorOutput())->toEndWith(progressLocations())->not->toContain('  › ', 'Failed asserting');
    $report = file_get_contents($file);
    expect($report)->toContain('--- Expected', '+++ Actual', "\nStack trace\n");
    foreach (['2', '3'] as $level) {
        $names = progressFixture([...$args, '--failures-verbosity='.$level]);
        expect($names->getExitCode())->toBe($compact->getExitCode())
            ->and($names->getOutput())->toContain('Tests:')->not->toContain('FAILED')
            ->and($names->getErrorOutput())->toEndWith(progressFailureLabels())->not->toContain('Failed asserting', 'Stack trace', '--- Expected')
            ->and(file_get_contents($file))->toBe($report);
    }
})->with('progress runners');

it('shows the native failure details on request without duplicating diagnostics', function (array $runner) {
    $process = progressFixture(['--progress', ...$runner, '--show-failure-details', '--filter=checks through a helper'],
        ['PEST_SHOW_DETAILS' => '0']);
    expect($process->getOutput())->toContain('FAILED', 'Failed asserting', 'Tests:')
        ->and($process->getErrorOutput())->toEndWith("tests-fixtures/suite/FailureTest.php:12  › it checks through a helper\n")
        ->not->toContain('Failed asserting');
})->with('progress runners');

it('writes the default report in the project test directory only during enabled runs', function (string $testDirectory) {
    // Run the installed Pest entry point with an isolated project root and a nested working directory.
    $installedVendor = dirname(__DIR__).'/vendor';
    $projectVendor = $this->temp.'/vendor';
    mkdir($projectVendor.'/bin', 0700, true);
    mkdir($projectVendor.'/pestphp/pest/bin', 0700, true);
    foreach (['bin/pest', 'pestphp/pest/bin/pest', 'pest-plugins.json'] as $path) {
        copy($installedVendor.'/'.$path, $projectVendor.'/'.$path);
    }
    file_put_contents($projectVendor.'/autoload.php', '<?php return require '.var_export($installedVendor.'/autoload.php', true).';');
    $cwd = $this->temp.'/'.$testDirectory.'/nested';
    mkdir($cwd, 0700, true);
    $file = $this->temp.'/'.$testDirectory.'/pest-failures.txt';
    $run = fn (array $args): Process => progressFixture($args, ['PEST_FAILURES_FILE' => false], $cwd, $projectVendor.'/bin/pest', $testDirectory);

    $native = $run(['--filter=reports an exception']);
    expect($native->getExitCode())->not->toBe(0)
        ->and($native->getOutput())->toContain('FAILED', 'Fixture exception')
        ->and(is_file($file))->toBeFalse();
    $failed = $run(['--progress', '--filter=reports an exception']);
    expect($failed->getExitCode())->not->toBe(0)
        ->and(is_file($cwd.'/pest-failures.txt'))->toBeFalse()
        ->and(file_get_contents($file))->toContain(dirname(__DIR__).'/tests-fixtures/suite/ErrorTest.php:6', 'Fixture exception');
    $report = file_get_contents($file);
    $native = $run(['--group=passing']);
    expect($native->getExitCode())->toBe(0)->and(file_get_contents($file))->toBe($report);
    foreach (['--group=passing', '--filter=passes each example'] as $filter) {
        $passed = $run(['--progress', $filter]);
        expect($passed->getExitCode())->toBe(0)
            ->and($passed->getErrorOutput())->toContain('10/10 (100%)')->toEndWith("No failed tests.\n")
            ->and(is_file($file))->toBeFalse();
    }
})->with(['tests', 'spec']);

it('honors the environment and gives file options on the command line priority', function () {
    $envFile = $this->temp.'/from-env';
    $cliFile = $this->temp.'/from-cli';
    $env = ['PEST_PROGRESS' => '1', 'PEST_FAILURES_FILE' => $envFile, 'PEST_SHOW_DETAILS' => '1', 'PEST_FAILURES_VERBOSITY' => '1'];
    $process = progressFixture(['--filter=checks through a helper', '--failures-file=from-cli', '--hide-failure-details', '--failures-verbosity=2'], $env, $this->temp);
    expect(is_file($envFile))->toBeFalse()
        ->and(file_get_contents($cliFile))->toContain('it checks through a helper', '--- Expected')
        ->and($process->getErrorOutput())->toContain('  › it checks through a helper')->not->toContain('Failed asserting')
        ->and($process->getOutput())->not->toContain('FAILED');
    $suppressed = progressFixture(['--no-failures-file', '--filter=reports an exception'], $env);
    expect($suppressed->getErrorOutput())->toEndWith("tests-fixtures/suite/ErrorTest.php:6\n")->not->toContain('written to')
        ->and($suppressed->getOutput())->toContain('FAILED', 'Fixture exception')
        ->and(is_file($envFile))->toBeFalse();
});

it('uses the native printer and exit status without activation or when bypassed', function (array $args, array $options, array $env) {
    $file = $this->temp.'/existing';
    file_put_contents($file, 'sentinel');
    $native = progressFixture($args, binary: progressNativeBinary($this->temp));
    $process = progressFixture([...$args, ...$options], $env + ['PEST_FAILURES_FILE' => $file]);
    $normalize = static fn (string $s): string => (string) preg_replace(
        ['/(Duration:(?:\s|\x1b\[[\d;]*m)*)[\d.]+s/', "/duration='\d+'/", "/timestamp='[^']+'/", "/flowId='\d+'/"],
        ['$1<time>', '<time>', '<time>', '<process>'], $s);
    expect($process->getExitCode())->toBe($native->getExitCode())
        ->and($normalize($process->getOutput()))->toBe($normalize($native->getOutput()))
        ->and($process->getErrorOutput())->toBe($native->getErrorOutput())
        ->and(file_get_contents($file))->toBe('sentinel');
})->with([
    'default' => [['--filter=checks through a helper', '--compact'], [], []],
    'default parallel' => [['--group=passing', '--compact', '--parallel', '--processes=2'], [], []],
    'display options alone' => [['--group=passing', '--compact'], ['--failures-verbosity=1', '--show-failure-details'], []],
    'literal filter' => [['--filter', '--progress'], [], []],
    'positional argument' => [['--', '--progress'], [], []],
    'environment with explicit opt-out' => [['--group=passing', '--compact'], ['--no-progress-counter'], ['PEST_PROGRESS' => '1']],
    'both flags' => [['--group=passing', '--compact'], ['--no-progress-counter', '--progress'], []],
    'teamcity' => [['--group=passing', '--teamcity'], ['--progress'], []],
    'list tests' => [['--list-tests'], ['--progress'], []],
]);

it('respects the environment opt-out and keeps a previous list', function () {
    $file = $this->temp.'/existing';
    file_put_contents($file, 'sentinel');
    $process = progressFixture(['--group=passing', '--parallel', '--processes=2', '--failures-file='.$file], ['PEST_PROGRESS' => '0']);
    expect($process->getExitCode())->toBe(0)
        ->and($process->getErrorOutput())->toBe('')
        ->and(file_get_contents($file))->toBe('sentinel');
});

it('passes --progress through when it is the value of a native reporting option', function () {
    $process = progressFixture(['--group=passing', '--log-junit', '--progress'], cwd: $this->temp);
    expect($process->getExitCode())->toBe(0)
        ->and($process->getErrorOutput())->toBe('')
        ->and(file_get_contents($this->temp.'/--progress'))->toContain('<testsuite');
});

it('reports a partial run while preserving Pest’s stop condition', function (array $runner) {
    $process = progressFixture(['--progress', '--bail', ...$runner, '--hide-failure-details']);
    expect($process->getErrorOutput())->toMatch('/Stopped early: [1-9]\d* \/ 19/')
        ->and($process->getOutput())->toContain('Tests:');
})->with(['sequential' => [[]], 'pending parallel files' => [['--parallel', '--processes=1']]]);

it('attributes a provider error to its own test and counts only executable cases', function (array $runner, bool $workerOnly) {
    $file = 'tests-fixtures/edge/ProviderTest.php';
    $result = $this->temp.'/providers.txt';
    $env = ['PROGRESS_FIXTURE_INVALID_PROVIDER' => $workerOnly ? false : '1'];
    $native = progressFixture([...$runner, $file], $env, binary: progressNativeBinary($this->temp));
    $process = progressFixture(['--progress', ...$runner, $file, '--failures-file='.$result], $env);
    $report = file_get_contents($result);
    // Some Pest versions turn a rejected provider into one executable test that throws.
    $discoveryError = str_contains($report, 'is invalid');
    $completed = $discoveryError ? 2 : 3;
    $total = $workerOnly ? 4 : $completed;
    $line = $discoveryError ? 9 : 13;
    $error = $process->getErrorOutput();
    expect($process->getExitCode())->toBe($native->getExitCode())->not->toBe(0)
        ->and($process->getOutput())->toContain('2 passed')
        ->and($error)->toContain("[progress] $completed/$total ", 'failed=0 errors=1 skipped=0 passed=2')
        ->toEndWith("Failed tests (1) — written to $result\n\n$file:$line  › it checks each scheme\n")
        ->not->toContain('Stopped early', 'runs before the dataset')
        ->and($report)->toContain('[1/1] ERROR - it checks each scheme', 'Fixture provider could not load the schemes');
    if ($workerOnly) {
        $note = "Recorded $completed of 4 discovered tests.".($discoveryError ? ' 1 error during test discovery.' : '');
        expect($error)->toContain($note)->and($report)->toContain($note);
    }
})->with([
    'worker discovery differs from parent' => [['--parallel', '--processes=2'], true],
    'all files visited with stop on failure enabled' => [['--parallel', '--processes=2', '--stop-on-failure'], true],
    'invalid during parent and worker discovery' => [['--parallel', '--processes=2'], false],
    'invalid during sequential discovery' => [[], false],
]);

it('keeps the filtered parallel total and Pest’s success exit status', function () {
    $process = progressFixture(['--parallel', '--progress', '--processes=2', '--group=passing'], ['PEST_PROGRESS' => '0']);
    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('10 passed')
        ->and($process->getErrorOutput())->toContain('10/10 (100%)')->toEndWith("No failed tests.\n");
});

it('keeps the profile before the final success message', function () {
    $process = progressFixture(['--progress', '--group=passing', '--profile']);
    expect($process->getOutput())->toContain('Top 10 slowest tests:')
        ->and($process->getErrorOutput())->toEndWith("No failed tests.\n");
});

it('records errors outside test bodies', function (string $name, int $line, array $runner) {
    $file = 'tests-fixtures/edge/'.$name.'Test.php';
    $result = $this->temp.'/failures';
    $process = progressFixture(['--progress', ...$runner, $file, '--hide-failure-details', '--failures-file='.$result]);
    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getErrorOutput())->toContain('errors=1', "$file:$line  › ")
        ->and(file_get_contents($result))->toContain("$file:$line\n", $name === 'BeforeAll' ? 'Fixture setup failed' : 'Fixture could not load');
})->with([
    ['BeforeAll', 6, []], ['LoadError', 5, []],
    ['BeforeAll', 6, ['--parallel', '--processes=2']], ['LoadError', 5, ['--parallel', '--processes=2']],
]);

it('preserves SIGINT behavior and writes the partial list', function (array $runner) {
    $root = dirname(__DIR__);
    $ready = $this->temp.'/ready';
    $result = $this->temp.'/partial.txt';
    $interrupt = function (array $args) use ($root, $ready): Process {
        $process = new Process([PHP_BINARY, $root.'/vendor/bin/pest', '--test-directory=tests-fixtures',
            '--configuration='.$root.'/tests-fixtures/phpunit.xml', '--colors=never', 'tests-fixtures/edge/InterruptTest.php', ...$args],
            $root, ['PROGRESS_FIXTURE_READY' => $ready, 'PEST_PROGRESS' => false, 'PARATEST' => false]);
        $process->start();
        $deadline = microtime(true) + 10;
        while (! is_file($ready) && $process->isRunning() && microtime(true) < $deadline) {
            usleep(10000);
        }
        expect(is_file($ready))->toBeTrue();
        $process->signal(SIGINT);
        $process->wait();
        unlink($ready);

        return $process;
    };
    $native = $interrupt($runner);
    $process = $interrupt(['--progress', ...$runner, '--failures-file='.$result]);
    expect($process->getExitCode())->toBe($native->getExitCode())
        ->and($process->getErrorOutput())->toContain('Interrupted:')->toEndWith("tests-fixtures/edge/InterruptTest.php:6  › it records a failure before interruption\n")
        ->and(file_get_contents($result))->toContain('Interrupted:', "tests-fixtures/edge/InterruptTest.php:6\n", 'Failed asserting');
})->with(['sequential' => [[]], 'parallel' => [['--parallel', '--processes=2']]])
    ->skip(! function_exists('pcntl_signal') || ! function_exists('posix_kill'), 'Requires Unix signals');

it('redraws intermediate counts before a parallel worker finishes its file', function () {
    $process = new Process(['sh', '-c', 'exec "$@" 2>&1', 'pest-tty', PHP_BINARY, 'vendor/bin/pest', '--test-directory=tests-fixtures', '--configuration=tests-fixtures/phpunit.xml',
        '--progress', '--compact', '--parallel', '--processes=2', '--no-failures-file', 'tests-fixtures/edge/LiveTest.php'],
        dirname(__DIR__), ['TERM' => 'xterm', 'NO_COLOR' => '1', 'CI' => false, 'PEST_PROGRESS' => false, 'PARATEST' => false]);
    $process->setPty(true);
    $process->run();
    $output = $process->getOutput().$process->getErrorOutput();
    expect($process->getExitCode())->toBe(0)
        ->and($output)->toMatch('/ [1-4] \/ 5 \(/')->toContain("\033[8F", '5 / 5 (100%)', 'No failed tests.')
        ->not->toContain("\033[32m")
        ->and(strpos($output, '5 / 5 (100%)'))->toBeLessThan(strpos($output, 'Tests:'))
        ->and(strpos($output, 'Tests:'))->toBeLessThan(strpos($output, 'No failed tests.'));
})->skip(PHP_OS_FAMILY === 'Windows' || ! Process::isPtySupported(), 'Requires a Unix pseudo-terminal');

it('finishes after listing named failures without waiting for terminal input', function (bool $redirect, array $env) {
    $command = $redirect ? 'exec "$@" </dev/null 2>&1' : 'exec "$@" 2>&1';
    $input = new InputStream;
    $file = $this->temp.'/failures.txt';
    $process = new Process(['sh', '-c', $command, 'pest-tty', PHP_BINARY, 'vendor/bin/pest', '--configuration=tests-fixtures/phpunit.xml',
        '--progress', '--test-directory=tests-fixtures', '--parallel', '--processes=2', '--filter=rejects the value', '--failures-file='.$file],
        dirname(__DIR__), $env + ['TERM' => 'xterm', 'CI' => false, 'NO_COLOR' => '1', 'PARATEST' => false,
            'PEST_FAILURES_VERBOSITY' => false, 'PEST_SHOW_DETAILS' => false], $input, 15);
    $process->setPty(true);
    try {
        $process->run();
    } finally {
        $input->close();
    }
    $output = $process->getOutput().$process->getErrorOutput();
    expect($process->getExitCode())->toBe(1)
        ->and($output)->toContain('Failed tests (2)', 'FailureTest.php:8  › it rejects the value with dataset "first"', 'dataset "second"')
        ->not->toContain('expand/collapse', 'Failed asserting', 'Stack trace')
        ->and(file_get_contents($file))->toContain('Failed asserting that 2 is identical to 0.', "\nStack trace\n");
    if (isset($env['CI'])) {
        expect($output)->toContain('[progress]')->not->toContain("\033[8F");
    }
})->with([
    'interactive terminal' => [false, []],
    'former verbosity setting' => [false, ['PEST_FAILURES_VERBOSITY' => '3']],
    'redirected stdin' => [true, []],
    'CI with a terminal' => [false, ['CI' => '1']],
])
    ->skip(PHP_OS_FAMILY === 'Windows' || ! Process::isPtySupported(), 'Requires a Unix pseudo-terminal');
