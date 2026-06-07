<?php

namespace App\Support;

final class EnvFile
{
    public function __construct(
        private readonly string $path,
    ) {}

    public static function at(string $path): self
    {
        return new self($path);
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (! $this->exists()) {
            return $default;
        }

        $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

        if (! preg_match($pattern, file_get_contents($this->path) ?: '', $matches)) {
            return $default;
        }

        $value = trim($matches[1]);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value !== '' ? $value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        if (! $this->exists()) {
            throw new \RuntimeException(".env not found at {$this->path}");
        }

        $contents = file_get_contents($this->path) ?: '';
        $line = $key.'='.$this->formatValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            $contents = (string) preg_replace($pattern, $line, $contents, 1);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($this->path, $contents);
    }

    private function formatValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\']/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
