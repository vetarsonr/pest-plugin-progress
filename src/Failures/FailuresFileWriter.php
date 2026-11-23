<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Failures;

final class FailuresFileWriter
{
    public static function write(string $path, string $contents): bool
    {
        if ($contents === '') {
            return (! is_file($path) && ! is_link($path)) || @unlink($path);
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            return false;
        }
        $temp = @tempnam($directory, '.pest-failures-');
        if ($temp === false) {
            return false;
        }
        try {
            return realpath(dirname($temp)) === realpath($directory)
                && @file_put_contents($temp, $contents) === strlen($contents)
                && @rename($temp, $path);
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
