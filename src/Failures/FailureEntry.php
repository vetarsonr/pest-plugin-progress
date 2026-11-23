<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Failures;

use RuntimeException;
use SplFileObject;
use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\Status;

final class FailureEntry
{
    public readonly string $path;

    public readonly ?int $line;

    public readonly string $name;

    public readonly string $kind;

    public readonly string $message;

    private readonly string $trace;

    private readonly string $prefix;

    public function __construct(private readonly Result $result, string $cwd)
    {
        $this->prefix = rtrim(str_replace('\\', '/', $cwd), '/').'/';
        $path = str_replace('\\', '/', $result->file);
        $this->path = $this->displayPath($path);
        $this->line = $result->line;
        $this->name = trim((string) preg_replace('/\s+/u', ' ', $this->plain($result->name)));
        $this->kind = $result->status === Status::Errors ? 'Error' : 'Failure';
        $this->message = trim($this->plain($result->message), "\n");
        $trace = $this->plain($result->trace);
        // PHPUnit stack frames are one file:line per line. Keep paths in the project portable.
        $this->trace = trim((string) preg_replace('~^'.preg_quote($this->prefix, '~').'(?=[^\n]+:\d+$)~m', '', $trace), "\n");
    }

    public function location(): string
    {
        return $this->path.($this->line === null ? '' : ':'.$this->line);
    }

    public function label(): string
    {
        return $this->location().'  › '.$this->name;
    }

    /** @return array<string, string> */
    public function details(): array
    {
        return array_filter([
            'Source' => $this->readSourceLine(),
            'Diagnostic' => $this->message,
            'Stack trace' => $this->trace,
        ], static fn (string $value): bool => $value !== '');
    }

    private function readSourceLine(): string
    {
        $path = str_replace('\\', '/', $this->result->file);
        $source = str_starts_with($path, '/') || preg_match('~^[a-z]:/~i', $path) ? $path : $this->prefix.$path;
        $number = $this->line;
        // PHPUnit gives the throwing frame first, including when an assertion lives in a helper.
        if (preg_match('~\A((?:/|[a-z]:/)[^\r\n]+):([1-9]\d*)\r?(?:\n|\z)~i', str_replace('\\', '/', $this->result->trace), $frame)
            && is_file($frame[1])) {
            $source = $frame[1];
            $number = (int) $frame[2];
        }
        if ($number === null || $number < 1 || ! is_file($source)) {
            return '';
        }
        try {
            $file = new SplFileObject($source);
            $file->seek($number - 1);
            $line = $file->current();
            if (! is_string($line) || trim($line) === '') {
                return '';
            }
            $location = $this->displayPath($source).':'.$number;
            $origin = $location === $this->location() ? '' : '// '.$location."\n";

            return $origin.trim($this->plain($line));
        } catch (RuntimeException) {
            return '';
        }
    }

    private function displayPath(string $path): string
    {
        $path = str_starts_with($path, $this->prefix) ? substr($path, strlen($this->prefix)) : $path;

        return str_replace(["\n", "\t"], ['\n', '\t'], $this->plain($path));
    }

    private function plain(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = (string) preg_replace('/\x1B(?:\[[0-?]*[ -\/]*[@-~]|\][^\x07\x1B]*(?:\x07|\x1B\\\\))/', '', $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $value);
    }
}
