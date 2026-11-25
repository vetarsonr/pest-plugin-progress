<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Transport;

use Vendor\PestProgress\Collector\Result;
use Vendor\PestProgress\Collector\RunState;
use Vendor\PestProgress\Collector\Status;

final class EventLog
{
    private int $offset = 0;

    public function __construct(public readonly string $directory) {}

    public function append(Result $result): void
    {
        $record = [
            'status' => $result->status->value, 'file' => $result->file, 'line' => $result->line,
            'name' => $result->name, 'message' => $result->message, 'worker' => (string) getenv('TEST_TOKEN'),
            'external' => $result->external, 'trace' => $result->trace,
        ];
        if ($result->discoveryId !== null) {
            $record['discoveryId'] = $result->discoveryId;
        }
        if ($result->testClass !== null) {
            $record['testClass'] = $result->testClass;
        }
        if ($result->testFile !== null) {
            $record['testFile'] = $result->testFile;
        }
        $this->write($record);
    }

    public function total(int $total): void
    {
        $this->write(['total' => $total]);
    }

    public function aborted(): void
    {
        $this->write(['aborted' => true]);
    }

    /** @param array<string, int|string|bool|null> $data */
    private function write(array $data): void
    {
        $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (@file_put_contents($this->directory.'/events.jsonl', $json."\n", FILE_APPEND | LOCK_EX) === false) {
            fwrite(STDERR, "[progress] Could not append to the worker event log.\n");
        }
    }

    public function read(RunState $state): void
    {
        $stream = @fopen($this->directory.'/events.jsonl', 'rb');
        if ($stream === false) {
            return;
        }
        try {
            if (! flock($stream, LOCK_SH) || fseek($stream, $this->offset) !== 0) {
                return;
            }
            while (($line = fgets($stream)) !== false && str_ends_with($line, "\n")) {
                $this->offset += strlen($line);
                $record = json_decode($line, true);
                if (! is_array($record)) {
                    continue;
                }
                if (isset($record['total']) && is_int($record['total'])) {
                    $state->total = $record['total'];

                    continue;
                }
                if (($record['aborted'] ?? false) === true) {
                    $state->aborted = true;

                    continue;
                }
                $status = is_string($record['status'] ?? null) ? Status::tryFrom($record['status']) : null;
                if ($status !== null && is_string($record['file'] ?? null) && is_string($record['name'] ?? null) && is_string($record['message'] ?? null)) {
                    $state->record(new Result($status, $record['file'], is_int($record['line'] ?? null) ? $record['line'] : null,
                        $record['name'], $record['message'], ($record['external'] ?? false) === true,
                        is_string($record['trace'] ?? null) ? $record['trace'] : '',
                        is_string($record['discoveryId'] ?? null) ? $record['discoveryId'] : null,
                        is_string($record['testClass'] ?? null) ? $record['testClass'] : null,
                        is_string($record['testFile'] ?? null) ? $record['testFile'] : null));
                }
            }
        } finally {
            fclose($stream);
        }
    }
}
