<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides which date a video belongs to on the calendar.
 *
 * If the title contains a date written as "YYYY-MM-DD" or "YYYY MM DD" that date
 * wins (combined with the upload's time of day, so videos keep their upload order
 * within a day). Otherwise the upload date is used.
 */
final class EffectiveDate
{
    private const PATTERN = '/(?<!\d)(\d{4})[- ](\d{2})[- ](\d{2})(?!\d)/';

    /** @param string $uploadedAt any ISO-8601 timestamp, e.g. 2026-11-16T00:47:38Z or ...38.000Z */
    public static function resolve(string $title, string $uploadedAt): string
    {
        $upload = (new DateTimeImmutable($uploadedAt))->setTimezone(new DateTimeZone('UTC'));

        $titleDate = self::fromTitle($title);
        if ($titleDate === null) {
            return $upload->format('Y-m-d\TH:i:s\Z');
        }

        return $titleDate . $upload->format('\TH:i:s\Z');
    }

    /** First valid date found in the title as "YYYY-MM-DD", or null. */
    public static function fromTitle(string $title): ?string
    {
        if (!preg_match_all(self::PATTERN, $title, $all, PREG_SET_ORDER)) {
            return null;
        }
        foreach ($all as [, $y, $m, $d]) {
            if ($y >= 1990 && $y <= (int) date('Y') + 1 && checkdate((int) $m, (int) $d, (int) $y)) {
                return "$y-$m-$d";
            }
        }
        return null;
    }
}
