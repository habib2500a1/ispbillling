<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class EnvWriter
{
    public static function path(): string
    {
        return base_path('.env');
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function setMany(array $values): void
    {
        $path = self::path();

        if (! File::exists($path)) {
            $template = base_path('deploy/.env.cpanel.example');
            if (is_file($template)) {
                File::copy($template, $path);
            } else {
                File::copy(base_path('.env.example'), $path);
            }
        }

        $contents = File::get($path);

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $contents = self::setLine($contents, $key, self::escape($value));
        }

        File::put($path, $contents);
    }

    public static function set(string $key, string $value): void
    {
        self::setMany([$key => $value]);
    }

    private static function setLine(string $contents, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        $line = $key.'='.$value;

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $line, $contents);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    private static function escape(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"$\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
