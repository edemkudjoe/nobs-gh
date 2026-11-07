<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

/** All settings as [name => value]. Loaded once per request. */
function settings_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = [];
        foreach (db_all('SELECT name, value FROM settings') as $row) {
            $all[$row['name']] = $row['value'];
        }
    }
    return $all;
}

/** One setting as text. Use the default only for a setting that does not exist. */
function setting(string $name, ?string $default = null): string
{
    $all = settings_all();
    if (!array_key_exists($name, $all)) {
        if ($default === null) {
            throw new RuntimeException("Unknown setting: $name");
        }
        return $default;
    }
    return $all[$name];
}

function setting_int(string $name): int
{
    return (int)setting($name);
}

function setting_float(string $name): float
{
    return (float)setting($name);
}

/** Create or change a setting (admin settings page). */
function setting_set(string $name, string $value): void
{
    db_exec('INSERT INTO settings (name, value) VALUES (?, ?)
             ON CONFLICT (name) DO UPDATE SET value = EXCLUDED.value', [$name, $value]);
    settings_all(true);
}
