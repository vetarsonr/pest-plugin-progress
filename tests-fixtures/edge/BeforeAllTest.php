<?php

declare(strict_types=1);

beforeAll(function () {
    throw new RuntimeException('Fixture setup failed');
});

it('requires setup', function () {
    expect(true)->toBeTrue();
});
