<?php

declare(strict_types=1);

it('skips the first case')->skip('unavailable');
it('skips the second case')->skip('unavailable');
it('is planned')->todo();

it('is incomplete', function () {
    $this->markTestIncomplete('unfinished');
});

it('makes no assertions', function () {});
