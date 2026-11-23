<?php

declare(strict_types=1);

it('records a failure before interruption', function () {
    expect(false)->toBeTrue();
});

it('signals that the long test has started', function () {
    $ready = getenv('PROGRESS_FIXTURE_READY');
    if (is_string($ready)) {
        touch($ready);
        usleep(750000);
    }
    expect(true)->toBeTrue();
});

it('would run after the long test', function () {
    expect(true)->toBeTrue();
});
