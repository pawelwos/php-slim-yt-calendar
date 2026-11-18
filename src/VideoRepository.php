<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use PDO;

final class VideoRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Number of videos per day for a month.
     *
     * effective_date mixes "YYYY-MM-DD" and "YYYY-MM-DDTHH:MM:SSZ", so the day is
     * the first 10 characters. Plain string range comparison keeps the index usable.
     *
     * @return array<string,int> 'YYYY-MM-DD' => count
     */
    public function countsForMonth(int $year, int $month): array
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT substr(effective_date, 1, 10) AS day, COUNT(*) AS cnt
            FROM youtube
            WHERE effective_date >= :start AND effective_date < :end
            GROUP BY day
        SQL);
        $stmt->execute([
            'start' => $start->format('Y-m-d'),
            'end' => $start->modify('+1 month')->format('Y-m-d'),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** @return list<array{videoId:string,title:string,source:?string}> */
    public function forDay(string $date): array
    {
        $day = new DateTimeImmutable($date);
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT videoId, title, source
            FROM youtube
            WHERE effective_date >= :start AND effective_date < :end
            ORDER BY effective_date, title
        SQL);
        $stmt->execute([
            'start' => $day->format('Y-m-d'),
            'end' => $day->modify('+1 day')->format('Y-m-d'),
        ]);

        return $stmt->fetchAll();
    }

    public function exists(string $videoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM youtube WHERE videoId = :id');
        $stmt->execute(['id' => $videoId]);
        return $stmt->fetchColumn() !== false;
    }

    /** Insert a video unless its ID is already present. Returns true if a row was added. */
    public function insertIfMissing(string $videoId, string $title, string $source, string $date, string $effectiveDate): bool
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT OR IGNORE INTO youtube (videoId, title, source, date, effective_date)
            VALUES (:id, :title, :source, :date, :effective)
        SQL);
        $stmt->execute([
            'id' => $videoId,
            'title' => $title,
            'source' => $source,
            'date' => $date,
            'effective' => $effectiveDate,
        ]);

        return $stmt->rowCount() > 0;
    }
}
