<?php

declare(strict_types=1);

namespace App;

use DateTimeInterface;
use IntlDateFormatter;

/** Locale-aware date formatting and Polish plural forms. */
final class Localizer
{
    /** @var array<string,IntlDateFormatter> */
    private array $formatters = [];

    public function __construct(private string $locale, private string $timezone) {}

    /** ICU pattern, e.g. "LLLL y" -> "listopad 2026", "EEEE, d MMMM y" -> "środa, 18 listopada 2026". */
    public function format(DateTimeInterface|string $date, string $pattern): string
    {
        if (is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }
        $f = $this->formatters[$pattern] ??= new IntlDateFormatter(
            $this->locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $this->timezone,
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );
        return $f->format($date);
    }

    /** Polish plural: 1 film, 2-4 filmy, 0/5+ filmów (12-14 -> filmów). */
    public function plural(int $n, string $one, string $few, string $many): string
    {
        if ($n === 1) {
            return $one;
        }
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        return ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? $few : $many;
    }
}
