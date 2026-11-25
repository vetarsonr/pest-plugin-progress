<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Factories\TestCaseFactory;
use Pest\Factories\TestCaseMethodFactory;
use Pest\TestSuite;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use ReflectionClass;
use ReflectionFunction;
use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\Status;

final class TestLocation
{
    /** @var array<class-string, string> */
    private array $files = [];

    /** @param class-string $class */
    private function classFile(string $class): string
    {
        if (! isset($this->files[$class])) {
            $reflection = new ReflectionClass($class);
            $source = $reflection->getStaticPropertyValue('__filename', null);
            $this->files[$class] = is_string($source) ? $source : (string) $reflection->getFileName();
        }

        return $this->files[$class];
    }

    public function file(Test $test): string
    {
        if ($test instanceof TestMethod && is_subclass_of($test->className(), HasPrintableTestCaseName::class)) {
            return $this->classFile($test->className());
        }

        return $test->file();
    }

    public function result(Test $test, Status $status, ?Throwable $exception = null, string $message = '', bool $discovery = false): Result
    {
        $file = $this->file($test);
        $name = $test->id();
        $line = null;
        if ($test instanceof TestMethod) {
            $class = $test->className();
            $name = $test->testDox()->prettifiedMethodName();
            if (is_subclass_of($class, HasPrintableTestCaseName::class)) {
                if ($status->isFailure()) {
                    $line = $this->traceLine($exception, $file);
                    if ($discovery || $line === null) {
                        $factory = TestSuite::getInstance()->tests->get($file);
                        $method = $factory instanceof TestCaseFactory && $factory->hasMethod($test->methodName())
                            ? $factory->getMethod($test->methodName()) : null;
                        $name = $method->description ?? $name;
                        $line ??= $this->definitionLine($file, $method);
                    }
                    // Only the executing case owns Pest's printable name, including the dataset label.
                    if (! $discovery) {
                        try {
                            $name = $class::getLatestPrintableTestCaseMethodName() ?: $name;
                        } catch (\Error) {
                            // Pest 2 initializes the name when constructing the test case.
                        }
                    }
                }
            } else {
                $line = $this->traceLine($exception, $file) ?? ($test->line() ?: null);
            }
        } else {
            $line = $this->traceLine($exception, $file);
        }

        return new Result($status, $file, $line, $name, $exception?->description() ?? $message,
            $discovery, $this->trace($exception), $discovery ? $test->id() : null,
            $status->isFailure() && $test instanceof TestMethod ? $test->className() : null);
    }

    private function definitionLine(string $file, ?TestCaseMethodFactory $method): ?int
    {
        $closure = $method?->closure;
        if (! $closure instanceof \Closure) {
            return null;
        }
        $reflection = new ReflectionFunction($closure);

        return $reflection->getFileName() === $file ? ($reflection->getStartLine() ?: null) : null;
    }

    /** @param class-string $class */
    public function hook(string $class, string $method, Throwable $exception): Result
    {
        $testFile = $this->classFile($class);
        $file = $testFile;
        $line = $this->traceLine($exception, $file);
        if ($line === null && preg_match('/^(.+):(\d+)$/m', $exception->stackTrace(), $frame) === 1) {
            [, $file, $line] = $frame;
            $line = (int) $line;
        }

        return new Result(Status::Errors, $file, $line, $class.'::'.$method, $exception->description(), true, $this->trace($exception),
            testClass: $class, testFile: $testFile);
    }

    private function trace(?Throwable $exception): string
    {
        $trace = $exception?->stackTrace() ?? '';
        if ($exception?->hasPrevious()) {
            $trace .= "\nCaused by\n".$exception->previous()->asString();
        }

        return $trace;
    }

    private function traceLine(?Throwable $exception, string $file): ?int
    {
        $file = str_replace('\\', '/', $file);
        while ($exception instanceof Throwable) {
            foreach (explode("\n", str_replace('\\', '/', $exception->stackTrace())) as $frame) {
                if (preg_match('/^(.+):(\d+)$/D', rtrim($frame, "\r"), $match) && $match[1] === $file) {
                    return (int) $match[2];
                }
            }
            $exception = $exception->hasPrevious() ? $exception->previous() : null;
        }

        return null;
    }
}
