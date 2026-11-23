<?php

declare(strict_types=1);

it('runs before the dataset', function () {
    expect(true)->toBeTrue();
});

it('checks each scheme', function (string $scheme) {
    expect($scheme)->toBeIn(['http', 'https']);
})->with(function (): array {
    if (getenv('PARATEST') === '1' || getenv('PROGRESS_FIXTURE_INVALID_PROVIDER') === '1') {
        throw new RuntimeException('Fixture provider could not load the schemes');
    }

    return [['http'], ['https']];
});

it('runs after the dataset', function () {
    expect(true)->toBeTrue();
});
