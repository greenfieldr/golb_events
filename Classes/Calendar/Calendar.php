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

/**
 * A month calendar: the weeks of the grid (including the leading and
 * trailing days of the adjacent months) with the occurrences per day, and
 * the previous and next month for navigation.
 */
final readonly class Calendar
{
    /**
     * @param list<list<CalendarDay>> $weeks seven days each
     */
    public function __construct(
        public int $year,
        public int $month,
        public \DateTimeImmutable $firstDay,
        public array $weeks,
    ) {}

    public function getYear(): int
    {
        return $this->year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * Midnight of the first day of the month
     */
    public function getFirstDay(): \DateTimeImmutable
    {
        return $this->firstDay;
    }

    /**
     * @return list<list<CalendarDay>>
     */
    public function getWeeks(): array
    {
        return $this->weeks;
    }

    /**
     * The days of the first week, e.g. to render the weekday headers
     *
     * @return list<CalendarDay>
     */
    public function getWeekdays(): array
    {
        return $this->weeks[0] ?? [];
    }

    public function getPreviousYear(): int
    {
        return $this->month === 1 ? $this->year - 1 : $this->year;
    }

    public function getPreviousMonth(): int
    {
        return $this->month === 1 ? 12 : $this->month - 1;
    }

    public function getNextYear(): int
    {
        return $this->month === 12 ? $this->year + 1 : $this->year;
    }

    public function getNextMonth(): int
    {
        return $this->month === 12 ? 1 : $this->month + 1;
    }

    /**
     * Whether any day of the grid has an occurrence (Fluid: {calendar.events})
     */
    public function hasEvents(): bool
    {
        foreach ($this->weeks as $week) {
            foreach ($week as $day) {
                if ($day->hasOccurrences()) {
                    return true;
                }
            }
        }

        return false;
    }
}
