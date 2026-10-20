<?php

declare(strict_types=1);

namespace CentroMedico;

final class Config
{
    private static array $values = [];

    public static function load(string $file): void
    {
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                self::$values[$key] = trim($value, "\"'");
            }
        }

        date_default_timezone_set(self::get('APP_TIMEZONE', 'America/Guatemala'));
    }

    public static function get(string $key, string $default = ''): string
    {
        $environment = getenv($key);
        if ($environment !== false) {
            return $environment;
        }

        return self::$values[$key] ?? $default;
    }
}

