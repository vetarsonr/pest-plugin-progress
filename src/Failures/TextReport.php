<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Failures;

use PHPUnit\Event\Code\TestCollection;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\TextUI\Configuration\Configuration;
use ReflectionClass;
use Vendor\PestProgress\Compat\TestLocation;

final class TextReport
{
    public static function format(FailureList $failures, string $runNote = '', string $testDirectory = 'tests', ?string $configurationFile = null, ?TestCollection $discoveredTests = null): string
    {
        $count = count($failures->entries);
        if ($count === 0) {
            return '';
        }

        $sections = [sprintf('Failed tests (%d)', $count)];
        if ($runNote !== '') {
            $sections[] = $runNote;
        }
        $sections[] = "Locations\n".implode("\n", $failures->locations());
        $rerun = self::rerunCommand($failures, $testDirectory, $configurationFile, $discoveredTests);
        if ($rerun !== '') {
            $sections[] = "Rerun failed test files\n".$rerun;
        }
        $divider = str_repeat('-', 72);
        foreach ($failures->entries as $index => $entry) {
            $sections[] = implode("\n", [
                $divider,
                sprintf('[%d/%d] %s - %s', $index + 1, $count, strtoupper($entry->kind), $entry->name),
                $entry->location(),
            ]);
            foreach ($entry->details() as $label => $value) {
                // Indent each nonempty line so rules inside diagnostics stay inside the case.
                $sections[] = $label."\n".preg_replace('/^./m', '  $0', $value);
            }
        }

        return implode("\n\n", $sections)."\n";
    }

    private static function rerunCommand(FailureList $failures, string $testDirectory, ?string $configurationFile, ?TestCollection $discoveredTests): string
    {
        $patterns = [];
        $files = [];
        foreach ($failures->entries as $entry) {
            $name = $entry->filterName();
            if ($entry->testFile === '') {
                continue;
            }
            if ($name !== '') {
                // Hex escapes keep punctuation literal in both the regex and the shell.
                $patterns[$name] ??= preg_replace_callback('/[^a-zA-Z0-9_]/',
                    static fn (array $match): string => sprintf('\x%02X', ord($match[0])), $name);
            }
            $path = str_starts_with($entry->testFile, '-') ? './'.$entry->testFile : $entry->testFile;
            $files[$entry->absoluteTestFile()] ??= self::shellArgument($path);
        }
        if ($files === []) {
            return '';
        }
        $options = '';
        if ($testDirectory !== 'tests') {
            $options .= ' --test-directory='.self::shellArgument($testDirectory);
        }
        if ($configurationFile !== null) {
            $options .= ' --configuration='.self::shellArgument($configurationFile);
        }
        $filter = implode('|', $patterns);
        if ($filter !== '' && $discoveredTests !== null && self::filterSelectsFiles($filter, $files, $discoveredTests)) {
            return './vendor/bin/pest --filter="'.$filter.'"'.$options;
        }

        $command = './vendor/bin/pest'.$options.' ';
        if (count($files) > 1 && ! (new ReflectionClass(Configuration::class))->hasMethod('cliArguments')) {
            // Early PHPUnit 10 accepts a single positional file per invocation.
            return implode("\n", array_map(static fn (string $file): string => $command.$file, $files));
        }

        return $command.implode(' ', $files);
    }

    /** @param array<string, string> $files Absolute test paths mapped to shell arguments. */
    private static function filterSelectsFiles(string $filter, array $files, TestCollection $tests): bool
    {
        $unseen = $files;
        $location = new TestLocation;
        $regex = '~'.$filter.'~i';
        foreach ($tests as $test) {
            if (! $test instanceof TestMethod) {
                // Pest versions differ on whether a name filter can select PHPT files.
                return false;
            }
            $file = str_replace('\\', '/', $location->file($test));
            if (isset($files[$file])) {
                if (@preg_match($regex, $test->className()) !== 1) {
                    return false;
                }
                unset($unseen[$file]);
            } elseif (@preg_match($regex, $test->id()."\n".$test->testDox()->prettifiedMethodName()) !== 0) {
                // Pest also matches method descriptions and dataset names.
                return false;
            }
        }

        return $unseen === [];
    }

    private static function shellArgument(string $value): string
    {
        return preg_match('~^[\w./:-]+$~D', $value) ? $value : escapeshellarg($value);
    }
}
