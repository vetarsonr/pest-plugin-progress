<?php

declare(strict_types=1);

use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\Status;
use Vendor\PestProgress\Failures\FailureList;
use Vendor\PestProgress\Failures\TextReport;

it('sorts lines numerically, deduplicates and preserves external paths', function () {
    $results = [
        new Result(Status::Failed, 'C:\src\T.php', 10, 'b'),
        new Result(Status::Errors, 'C:\src\T.php', 2, 'c'),
        new Result(Status::Failed, 'C:\src\T.php', 10, 'a'),
        new Result(Status::Failed, 'C:\src-other\X.php', null, 'x'),
    ];
    expect((new FailureList($results, 'C:\src'))->locations())->toBe(['C:/src-other/X.php', 'T.php:2', 'T.php:10']);
});

it('uses the owning test file when a diagnostic points into a helper', function () {
    $testFile = 'tests/Feature cases/CheckoutTest.php';
    $failures = new FailureList([
        new Result(Status::Errors, '/project/src/helper.php', 7, 'setup failed', testFile: '/project/'.$testFile),
    ], '/project/src', '/project');

    expect(TextReport::format($failures))->toContain("Locations\nhelper.php:7", './vendor/bin/pest '.escapeshellarg($testFile));
});

it('separates each case from its multiline diagnostic in the text report', function () {
    $divider = str_repeat('-', 72);
    $longLine = str_repeat('è', 240);
    $message = "\033[31m".$longLine."\033[0m\r\n```\n# literal text\n\n".$divider;
    $directory = str_replace('\\', '/', __DIR__);
    $file = $directory.'/missing.php';
    $failures = new FailureList([
        new Result(Status::Errors, $file, 8, 'other case', 'Something went wrong'),
        new Result(Status::Failed, $file, 8, 'case `Pest`', $message, trace: $file.":8\n"),
    ], $directory);

    $expected = <<<TEXT
        Failed tests (2)

        Locations
        missing.php:8

        Rerun failed test files
        ./vendor/bin/pest missing.php

        $divider
        [1/2] FAILURE - case `Pest`
        missing.php:8

        Diagnostic
          $longLine
          ```
          # literal text

          $divider

        Stack trace
          missing.php:8

        $divider
        [2/2] ERROR - other case
        missing.php:8

        Diagnostic
          Something went wrong
        TEXT;
    expect(TextReport::format($failures))->toBe($expected."\n");
});
