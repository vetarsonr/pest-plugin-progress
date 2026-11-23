<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Collector;

use Closure;
use PHPUnit\Event\Test;
use PHPUnit\Event\TestRunner\ExecutionAbortedSubscriber;
use Vendor\PestProgress\Collector\Subscribers as S;
use Vendor\PestProgress\Compat\PHPUnitEvents;

final class Subscribers
{
    /** @param (Closure(): void)|null $aborted */
    public static function register(ResultRecorder $recorder, ?Closure $aborted = null): void
    {
        PHPUnitEvents::register(
            new S\Passed($recorder),
            new S\Failed($recorder),
            new S\Errored($recorder),
            new S\Skipped($recorder),
            new S\MarkedIncomplete($recorder),
            new S\ConsideredRisky($recorder),
            new S\PhpunitErrorTriggered($recorder),
            new S\PreparationStarted($recorder),
            new S\Finished($recorder),
            new S\SuiteFinished($recorder),
            new S\SuiteSkipped($recorder),
            new S\BeforeFirstTestMethodErrored($recorder),
        );

        if ($aborted instanceof Closure && interface_exists(ExecutionAbortedSubscriber::class)) {
            PHPUnitEvents::register(new S\ExecutionAborted($aborted));
        }

        // PHPUnit added separate class-hook assertion failures and teardown errors after 10.x.
        foreach ([
            Test\BeforeFirstTestMethodFailedSubscriber::class => S\BeforeFirstTestMethodFailed::class,
            Test\AfterLastTestMethodFailedSubscriber::class => S\AfterLastTestMethodFailed::class,
            Test\AfterLastTestMethodErroredSubscriber::class => S\AfterLastTestMethodErrored::class,
        ] as $interface => $subscriber) {
            if (interface_exists($interface)) {
                PHPUnitEvents::register(new $subscriber($recorder));
            }
        }
    }
}
