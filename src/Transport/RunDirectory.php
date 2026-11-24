<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Transport;

final class RunDirectory
{
    private const ENV = 'PEST_PROGRESS_RUN';

    /** @var array<string, array{string|false, mixed}> */
    private array $previous = [];

    /** @param resource $lock */
    private function __construct(public readonly EventLog $log, private $lock, bool $showDetails)
    {
        // Symfony Process also reads $_ENV when composing the workers' environment.
        foreach ([self::ENV => $log->directory, 'PEST_SHOW_DETAILS' => $showDetails ? '1' : '0'] as $key => $value) {
            $this->previous[$key] = [getenv($key), $_ENV[$key] ?? null];
            $_ENV[$key] = $value;
            putenv($key.'='.$value);
        }
    }

    public static function create(bool $showDetails): ?self
    {
        $path = sys_get_temp_dir().'/pest-progress-'.bin2hex(random_bytes(16));
        if (! @mkdir($path, 0700)) {
            return null;
        }
        $lock = @fopen($path.'/owner.lock', 'x');
        if ($lock === false || ! flock($lock, LOCK_EX) || ! @touch($path.'/events.jsonl')) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            self::remove($path);

            return null;
        }

        return new self(new EventLog($path), $lock, $showDetails);
    }

    public static function workerLog(): ?EventLog
    {
        $path = getenv(self::ENV);

        return is_string($path) && is_file($path.'/events.jsonl') ? new EventLog($path) : null;
    }

    public static function clearInheritedLog(): void
    {
        unset($_ENV[self::ENV], $_SERVER[self::ENV]);
        if (getenv(self::ENV) !== false) {
            putenv(self::ENV);
        }
    }

    public function close(): void
    {
        foreach ($this->previous as $key => [$system, $env]) {
            if ($env === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            putenv($system === false ? $key : $key.'='.$system);
        }
        fclose($this->lock);
        self::remove($this->log->directory);
    }

    public static function prune(): void
    {
        foreach (glob(sys_get_temp_dir().'/pest-progress-*', GLOB_ONLYDIR) ?: [] as $path) {
            if (is_link($path) || ! preg_match('/^pest-progress-[a-f0-9]{32}$/D', basename($path))
                || @filemtime($path) >= time() - 86400 || is_link($path.'/owner.lock')) {
                continue;
            }
            $lock = @fopen($path.'/owner.lock', 'c');
            if ($lock !== false) {
                if (flock($lock, LOCK_EX | LOCK_NB)) {
                    self::remove($path);
                }
                fclose($lock);
            }
        }
    }

    private static function remove(string $path): void
    {
        @unlink($path.'/events.jsonl');
        @unlink($path.'/owner.lock');
        @rmdir($path);
    }
}
