<?php

declare(strict_types=1);

it('reports an exception', function () {
    throw new RuntimeException("Fixture exception\nThe record is missing its identifier.");
});
