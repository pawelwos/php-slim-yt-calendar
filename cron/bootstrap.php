<?php

declare(strict_types=1);

// Shared by the CLI scripts in this folder. Lives outside public_html, so it can't be requested over HTTP.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Warsaw');

/** Resolve a path from .env relative to the project root unless it is absolute. */
function project_path(string $root, string $path): string
{
    return str_starts_with($path, '/') ? $path : $root . '/' . $path;
}

function fail(string $message): never
{
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] ERROR: $message\n");
    exit(1);
}
