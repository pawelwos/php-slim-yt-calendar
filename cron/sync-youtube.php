#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Adds recent YouTube uploads that are not yet in the database.
 *
 *   php cron/sync-youtube.php [--days=14] [--dry-run] [--db=PATH]
 *
 * Crontab (daily; the look-back window also covers weekly runs and missed days):
 *   17 4 * * * php /path/to/project/cron/sync-youtube.php >> /path/to/project/storage/logs/sync.log 2>&1
 */

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\VideoRepository;
use App\YoutubeClient;
use App\YoutubeSync;

$opts = getopt('', ['days:', 'dry-run', 'db:', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php cron/sync-youtube.php [--days=14] [--dry-run] [--db=PATH]\n";
    exit(0);
}

$days = (int) ($opts['days'] ?? 7);
if ($days < 1 || $days > 3650) {
    fail('--days must be between 1 and 3650');
}
$dryRun = isset($opts['dry-run']);

$dbPath = isset($opts['db']) ? (string) $opts['db'] : project_path($root, $_ENV['DB_PATH'] ?? 'storage/youtube.db');
$tokenPath = project_path($root, $_ENV['YOUTUBE_TOKEN_PATH'] ?? 'storage/youtube-token.json');
$sourceName = $_ENV['YOUTUBE_SOURCE'] ?? 'pawel';

if (empty($_ENV['GOOGLE_CLIENT_ID']) || empty($_ENV['GOOGLE_CLIENT_SECRET'])) {
    fail('GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are not set in .env');
}

// Never run two syncs at once (e.g. a slow run overlapping the next cron tick).
@mkdir($root . '/storage/logs', 0775, true);
$lock = fopen($root . '/storage/sync.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fail('another sync is already running');
}

try {
    $pdo = Database::connect($dbPath);
    $sync = new YoutubeSync(
        new YoutubeClient($_ENV['GOOGLE_CLIENT_ID'], $_ENV['GOOGLE_CLIENT_SECRET'], $tokenPath),
        new VideoRepository($pdo),
        $pdo,
        $sourceName,
    );
    $result = $sync->run($days, $dryRun);
} catch (Throwable $e) {
    fail(get_class($e) . ': ' . $e->getMessage());
}

foreach ($result['rows'] as $r) {
    printf("  %s %s  %-30s  date=%s effective=%s\n", $dryRun ? '[would add]' : '[added]', $r['videoId'], mb_strimwidth($r['title'], 0, 30, '…'), $r['date'], $r['effective_date']);
}
printf(
    "[%s] %ssource=%s days=%d seen=%d added=%d already_present=%d\n",
    date('Y-m-d H:i:s'),
    $dryRun ? 'DRY RUN ' : '',
    $sourceName,
    $days,
    $result['seen'],
    $result['added'],
    $result['skipped'],
);
