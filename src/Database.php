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
        return new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
