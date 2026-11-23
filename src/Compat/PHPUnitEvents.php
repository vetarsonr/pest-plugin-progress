<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use PHPUnit\Event\Facade;
use PHPUnit\Event\Subscriber;
use ReflectionMethod;

final class PHPUnitEvents
{
    public static function register(Subscriber ...$subscribers): void
    {
        // PHPUnit 10.0 exposed registration as a static method.
        $register = new ReflectionMethod(Facade::class, 'registerSubscribers');
        $register->invokeArgs($register->isStatic() ? null : Facade::instance(), $subscribers);
    }
}
