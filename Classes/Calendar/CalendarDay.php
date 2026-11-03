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
 * One day of the calendar grid with the occurrences taking place on it.
 */
final class CalendarDay
{
    /**
     * @var Occurrence[]
     */
    private array $occurrences = [];

    /**
     * @param \DateTimeImmutable $date midnight of the day in the calendar's time zone
     * @param bool $inMonth whether the day belongs to the displayed month
     */
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly bool $inMonth,
        public readonly bool $today,
    ) {}

    public function addOccurrence(Occurrence $occurrence): void
    {
        $this->occurrences[] = $occurrence;
    }

    /**
     * @return Occurrence[]
     */
    public function getOccurrences(): array
    {
        return $this->occurrences;
    }

    public function hasOccurrences(): bool
    {
        return $this->occurrences !== [];
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function isInMonth(): bool
    {
        return $this->inMonth;
    }

    public function isToday(): bool
    {
        return $this->today;
    }

    /**
     * The day as Y-m-d, the key the builder files occurrences under
     */
    public function getKey(): string
    {
        return $this->date->format('Y-m-d');
    }
}
