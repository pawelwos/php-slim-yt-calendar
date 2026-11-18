<?php

declare(strict_types=1);

// Run: php tests/sync.php   (uses a throwaway copy of the database and a fake YouTube source)

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\VideoRepository;
use App\VideoSource;
use App\YoutubeSync;

$real = __DIR__ . '/../storage/youtube.db';
$copy = sys_get_temp_dir() . '/yt-sync-test-' . bin2hex(random_bytes(4)) . '.db';
copy($real, $copy);
register_shutdown_function(fn () => @unlink($copy));

$pdo = Database::connect($copy);
$repo = new VideoRepository($pdo);
$existing = $pdo->query('SELECT videoId, title, date FROM youtube LIMIT 1')->fetch();

$source = new class ($existing) implements VideoSource {
    public function __construct(private array $existing) {}
    public function recentUploads(int $days): array
    {
        return [
            ['videoId' => 'NEWdated0001', 'title' => 'kamera2 2026-11-10', 'publishedAt' => '2026-11-11T00:12:43Z'],
            ['videoId' => 'NEWplain0002', 'title' => 'Wojtuś i Franuś', 'publishedAt' => '2026-11-12T10:00:00Z'],
            ['videoId' => $this->existing['videoId'], 'title' => $this->existing['title'], 'publishedAt' => $this->existing['date']],
        ];
    }
};

$before = (int) $pdo->query('SELECT COUNT(*) FROM youtube')->fetchColumn();
$sync = new YoutubeSync($source, $repo, $pdo, 'pawel');
$failed = 0;
$check = function (string $name, bool $ok) use (&$failed) {
    $failed += $ok ? 0 : 1;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
};

$dry = $sync->run(14, dryRun: true);
$check('dry run reports 2 new, 1 already present', $dry['added'] === 2 && $dry['skipped'] === 1 && $dry['seen'] === 3);
$check('dry run writes nothing', (int) $pdo->query('SELECT COUNT(*) FROM youtube')->fetchColumn() === $before);

$run = $sync->run(14);
$check('real run adds 2 rows', $run['added'] === 2 && (int) $pdo->query('SELECT COUNT(*) FROM youtube')->fetchColumn() === $before + 2);

$row = $pdo->query("SELECT * FROM youtube WHERE videoId='NEWdated0001'")->fetch();
$check('title date used as effective_date (+ upload time)', $row['effective_date'] === '2026-11-10T00:12:43Z');
$check('source and upload date stored', $row['source'] === 'pawel' && $row['date'] === '2026-11-11T00:12:43Z');
$row = $pdo->query("SELECT * FROM youtube WHERE videoId='NEWplain0002'")->fetch();
$check('no title date -> upload date', $row['effective_date'] === '2026-11-12T10:00:00Z');

$again = $sync->run(14);
$check('second run adds nothing (idempotent)', $again['added'] === 0 && $again['skipped'] === 3);

$counts = $repo->countsForMonth(2026, 11);
$check('new videos show up in calendar counts on the effective day', ($counts['2026-11-10'] ?? 0) >= 1 && ($counts['2026-11-12'] ?? 0) >= 1);

echo $failed === 0 ? "\nAll passed\n" : "\n$failed failed\n";
exit($failed === 0 ? 0 : 1);
