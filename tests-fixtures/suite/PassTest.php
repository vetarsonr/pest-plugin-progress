<?php

declare(strict_types=1);

it('passes each example', function (int $value) {
    expect($value)->toBeGreaterThan(0);
})->with(range(1, 10))->group('passing');
