<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/helper.php';

it('rejects the value', function (int $value) {
    expect($value)->toBe(0);
})->with(['first' => 1, 'second' => 2]);

it('checks through a helper', function () {
    progressFixtureAssertion();
});
