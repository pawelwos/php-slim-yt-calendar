<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class CalendarController
{
    public function __construct(
        private Twig $view,
        private VideoRepository $videos,
        private DateTimeZone $tz,
        private Localizer $l10n,
    ) {}

    /** Homepage: this month + today's videos. */
    public function home(Request $request, Response $response): Response
    {
        $today = new DateTimeImmutable('today', $this->tz);

        return $this->view->render($response, 'home.twig', [
            'cal' => $this->month((int) $today->format('Y'), (int) $today->format('n'), $today->format('Y-m-d'), $today),
            'selected' => $today->format('Y-m-d'),
            'videos' => $this->videos->forDay($today->format('Y-m-d')),
        ]);
    }

    /** /calendar/{year}/{month}[/{day}] */
    public function calendar(Request $request, Response $response, array $args): Response
    {
        $year = (int) $args['year'];
        $month = (int) $args['month'];
        $day = isset($args['day']) ? (int) $args['day'] : null;

        if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12 || ($day !== null && !checkdate($month, $day, $year))) {
            return $response->withHeader('Location', '/calendar')->withStatus(302);
        }

        $today = new DateTimeImmutable('today', $this->tz);
        $selected = $day !== null ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;

        return $this->view->render($response, 'calendar.twig', [
            'cal' => $this->month($year, $month, $selected, $today),
            'selected' => $selected,
            'videos' => $selected !== null ? $this->videos->forDay($selected) : [],
        ]);
    }

    /** /calendar -> current month, today selected. */
    public function current(Request $request, Response $response): Response
    {
        $today = new DateTimeImmutable('today', $this->tz);
        return $response->withHeader('Location', '/calendar/' . $today->format('Y/n/j'))->withStatus(302);
    }

    private function month(int $year, int $month, ?string $selected, DateTimeImmutable $today): array
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $this->tz);
        $counts = $this->videos->countsForMonth($year, $month);
        $daysInMonth = (int) $first->format('t');
        $offset = (int) $first->format('N') - 1; // Monday first

        $cells = array_fill(0, $offset, null);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $cells[] = [
                'day' => $d,
                'date' => $date,
                'url' => sprintf('/calendar/%d/%d/%d', $year, $month, $d),
                'count' => $counts[$date] ?? 0,
                'isToday' => $date === $today->format('Y-m-d'),
                'isSelected' => $date === $selected,
            ];
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $prev = $first->modify('-1 month');
        $next = $first->modify('+1 month');
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = ['n' => $m, 'label' => $this->l10n->format(sprintf('2026-%02d-15', $m), 'LLL'), 'active' => $m === $month];
        }

        return [
            'year' => $year,
            'month' => $month,
            'title' => $this->l10n->format($first, 'LLLL y'),
            'weekdays' => ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'],
            'weeks' => array_chunk($cells, 7),
            'total' => array_sum($counts),
            'months' => $months,
            'prevYearUrl' => sprintf('/calendar/%d/%d', $year - 1, $month),
            'nextYearUrl' => sprintf('/calendar/%d/%d', $year + 1, $month),
            'prevMonthUrl' => $prev->format('/\c\a\l\e\n\d\a\r/Y/n'),
            'nextMonthUrl' => $next->format('/\c\a\l\e\n\d\a\r/Y/n'),
        ];
    }
}
