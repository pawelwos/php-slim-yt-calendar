<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        if (!is_file($path)) {
            throw new \RuntimeException("SQLite database not found: $path");
        }

        // Journal mode is left at the file's default so merely reading
        // doesn't modify the git-tracked database file.
        $pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Wait instead of failing if the sync job is writing while the site reads (or vice versa).
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }
}
