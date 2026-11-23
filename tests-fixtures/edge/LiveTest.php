<?php

declare(strict_types=1);

it('advances inside a single worker file', function (int $case) {
    usleep(180000);
    expect($case)->toBeGreaterThan(0);
})->with(range(1, 5));
