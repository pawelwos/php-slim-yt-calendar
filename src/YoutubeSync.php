<?php

declare(strict_types=1);

namespace App;

use PDO;

final class YoutubeSync
{
    public function __construct(
        private VideoSource $source,
        private VideoRepository $videos,
        private PDO $pdo,
        private string $sourceName,
    ) {}

    /**
     * @return array{seen:int,added:int,skipped:int,rows:list<array{videoId:string,title:string,date:string,effective_date:string}>}
     *         rows = the videos that were (or, with $dryRun, would be) added
     */
    public function run(int $days, bool $dryRun = false): array
    {
        $uploads = $this->source->recentUploads($days);
        $result = ['seen' => count($uploads), 'added' => 0, 'skipped' => 0, 'rows' => []];

        $this->pdo->beginTransaction();
        try {
            foreach ($uploads as $u) {
                if ($this->videos->exists($u['videoId'])) {
                    $result['skipped']++;
                    continue;
                }

                $row = [
                    'videoId' => $u['videoId'],
                    'title' => $u['title'],
                    'date' => $u['publishedAt'],
                    'effective_date' => EffectiveDate::resolve($u['title'], $u['publishedAt']),
                ];

                if (!$dryRun) {
                    $this->videos->insertIfMissing($row['videoId'], $row['title'], $this->sourceName, $row['date'], $row['effective_date']);
                }
                $result['added']++;
                $result['rows'][] = $row;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $result;
    }
}
