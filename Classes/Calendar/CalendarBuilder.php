<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Calendar;

use Greenfieldr\GolbEvents\Domain\Model\Occurrence;

/**
 * Builds the month grid of the calendar plugin and files the occurrences
 * under the days they take place on. Days are counted in the given time
 * zone, normally the one the site displays times in.
 */
final class CalendarBuilder
{
    /**
     * The grid starts with the first day of the week containing the first of
     * the month and ends with the last day of the week containing the last
     * of the month. Returns midnight of the first grid day and the last second
     * of the last grid day.
     *
     * @param int $firstDayOfWeek 1 = Monday ... 7 = Sunday (ISO 8601)
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function gridRange(int $year, int $month, \DateTimeZone $timezone, int $firstDayOfWeek = 1): array
    {
        $firstDayOfWeek = $this->normaliseFirstDayOfWeek($firstDayOfWeek);
        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $timezone);
        $lastOfMonth = $firstOfMonth->modify('last day of this month');

        $leading = ((int)$firstOfMonth->format('N') - $firstDayOfWeek + 7) % 7;
        $trailing = (7 - (((int)$lastOfMonth->format('N') - $firstDayOfWeek + 7) % 7) - 1) % 7;

        return [
            $firstOfMonth->modify('-' . $leading . ' days'),
            $lastOfMonth->modify('+' . $trailing . ' days')->setTime(23, 59, 59),
        ];
    }

    /**
     * @param Occurrence[] $occurrences occurrences overlapping the grid, in display order
     * @param \DateTimeImmutable|null $today reference day for the "today" flag
     */
    public function build(
        int $year,
        int $month,
        array $occurrences,
        \DateTimeZone $timezone,
        int $firstDayOfWeek = 1,
        ?\DateTimeImmutable $today = null
    ): Calendar {
        [$gridStart, $gridEnd] = $this->gridRange($year, $month, $timezone, $firstDayOfWeek);
        $todayKey = ($today ?? new \DateTimeImmutable())->setTimezone($timezone)->format('Y-m-d');

        /** @var array<string, CalendarDay> $days */
        $days = [];
        $weeks = [];
        $week = [];
        for ($date = $gridStart; $date <= $gridEnd; $date = $date->modify('+1 day')) {
            $day = new CalendarDay($date, (int)$date->format('n') === $month, $date->format('Y-m-d') === $todayKey);
            $days[$day->getKey()] = $day;
            $week[] = $day;
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        foreach ($occurrences as $occurrence) {
            foreach ($this->dayKeysOf($occurrence, $timezone, $gridStart, $gridEnd) as $key) {
                if (isset($days[$key])) {
                    $days[$key]->addOccurrence($occurrence);
                }
            }
        }

        return new Calendar($year, $month, $gridStart->modify('+' . (int)$gridStart->diff(new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $timezone))->days . ' days'), $weeks);
    }

    /**
     * The days (Y-m-d) an occurrence covers within the grid.
     *
     * @return string[]
     */
    private function dayKeysOf(Occurrence $occurrence, \DateTimeZone $timezone, \DateTimeImmutable $gridStart, \DateTimeImmutable $gridEnd): array
    {
        $start = $occurrence->getStart();
        if (!$start instanceof \DateTimeImmutable) {
            return [];
        }
        $end = $occurrence->getEnd() ?? $start;
        $first = max($start->setTimezone($timezone)->setTime(0, 0), $gridStart);
        $last = min($end->setTimezone($timezone)->setTime(0, 0), $gridEnd);

        $keys = [];
        for ($date = $first; $date <= $last; $date = $date->modify('+1 day')) {
            $keys[] = $date->format('Y-m-d');
        }

        return $keys;
    }

    private function normaliseFirstDayOfWeek(int $firstDayOfWeek): int
    {
        return $firstDayOfWeek >= 1 && $firstDayOfWeek <= 7 ? $firstDayOfWeek : 1;
    }
}
