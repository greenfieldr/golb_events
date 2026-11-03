<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Recurrence;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;

/**
 * The schedule of an event as needed to generate its occurrences: the wall
 * clock start and end, the time zone they refer to and the recurrence rule.
 * Built either from the Event model or from a raw database row.
 */
final readonly class EventSchedule
{
    /**
     * @param string[] $weekdays weekday codes (MO, TU, ...) of a weekly recurrence
     * @param string[] $exceptionDates excluded dates as Y-m-d
     */
    public function __construct(
        public \DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public bool $allDay,
        public \DateTimeZone $timezone,
        public string $frequency = Event::FREQUENCY_NONE,
        public int $interval = 1,
        public array $weekdays = [],
        public string $monthlyMode = Event::MONTHLY_MODE_DAY_OF_MONTH,
        public string $endMode = Event::RECURRENCE_END_NEVER,
        public ?\DateTimeImmutable $until = null,
        public int $count = 0,
        public array $exceptionDates = [],
    ) {}

    public static function fromEvent(Event $event): ?self
    {
        $start = $event->getStart();
        if (!$start instanceof \DateTimeImmutable) {
            return null;
        }
        $exceptionDates = [];
        foreach ($event->getExceptions() as $exception) {
            if ($exception instanceof RecurrenceException && $exception->getDate() instanceof \DateTimeImmutable) {
                $exceptionDates[] = $exception->getDate()->format('Y-m-d');
            }
        }

        return new self(
            $start,
            $event->getEnd(),
            $event->isAllDay(),
            $event->getTimezone(),
            $event->getRecurrenceFrequency(),
            $event->getRecurrenceInterval(),
            $event->getRecurrenceWeekdayList(),
            $event->getRecurrenceMonthlyMode(),
            $event->getRecurrenceEnd(),
            $event->getRecurrenceUntil(),
            $event->getRecurrenceCount(),
            $exceptionDates,
        );
    }

    /**
     * Builds the schedule from a "pages" row as stored in the database.
     *
     * @param array<string, mixed> $row
     * @param string $timezone IANA time zone of the location, empty for the server time zone
     * @param string[] $exceptionDates excluded dates as Y-m-d
     */
    public static function fromRecord(array $row, string $timezone = '', array $exceptionDates = []): ?self
    {
        $start = self::dateTimeFromRow($row['tx_golbevents_start'] ?? null);
        if (!$start instanceof \DateTimeImmutable) {
            return null;
        }
        $weekdays = array_map(trim(...), explode(',', strtoupper(self::stringFromRow($row['tx_golbevents_recurrence_weekdays'] ?? ''))));

        return new self(
            $start,
            self::dateTimeFromRow($row['tx_golbevents_end'] ?? null),
            (bool)($row['tx_golbevents_all_day'] ?? false),
            new \DateTimeZone($timezone !== '' ? $timezone : date_default_timezone_get()),
            self::stringFromRow($row['tx_golbevents_recurrence_frequency'] ?? Event::FREQUENCY_NONE),
            max(1, self::intFromRow($row['tx_golbevents_recurrence_interval'] ?? 1)),
            array_values(array_intersect(Event::WEEKDAYS, $weekdays)),
            self::stringFromRow($row['tx_golbevents_recurrence_monthly_mode'] ?? Event::MONTHLY_MODE_DAY_OF_MONTH),
            self::stringFromRow($row['tx_golbevents_recurrence_end'] ?? Event::RECURRENCE_END_NEVER),
            self::dateTimeFromRow($row['tx_golbevents_recurrence_until'] ?? null),
            self::intFromRow($row['tx_golbevents_recurrence_count'] ?? 0),
            $exceptionDates,
        );
    }

    public function isRecurring(): bool
    {
        return in_array(
            $this->frequency,
            [Event::FREQUENCY_DAILY, Event::FREQUENCY_WEEKLY, Event::FREQUENCY_MONTHLY, Event::FREQUENCY_YEARLY],
            true
        );
    }

    /**
     * The start as a real point in time: the wall clock start in the time zone
     * of the schedule, at midnight for all day events.
     */
    public function getStartInTimezone(): \DateTimeImmutable
    {
        return $this->wallClockInTimezone($this->allDay ? $this->start->setTime(0, 0) : $this->start);
    }

    /**
     * The end as a real point in time; the start if there is no end. All day
     * events end at the last second of their last day.
     */
    public function getEndInTimezone(): \DateTimeImmutable
    {
        $end = $this->end ?? $this->start;
        if ($this->allDay) {
            $end = $end->setTime(23, 59, 59);
        }
        $end = $this->wallClockInTimezone($end);
        $start = $this->getStartInTimezone();

        return $end < $start ? $start : $end;
    }

    /**
     * Interprets a wall clock time (as mapped from the database, in the server
     * time zone) in the time zone of the schedule.
     */
    public function wallClockInTimezone(\DateTimeImmutable $wallClock): \DateTimeImmutable
    {
        return new \DateTimeImmutable($wallClock->format('Y-m-d H:i:s'), $this->timezone);
    }

    private static function dateTimeFromRow(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        $dateTime = \DateTimeImmutable::createFromFormat(
            str_contains($value, ':') ? 'Y-m-d H:i:s' : '!Y-m-d',
            $value,
            new \DateTimeZone(date_default_timezone_get())
        );

        return $dateTime instanceof \DateTimeImmutable ? $dateTime : null;
    }

    private static function stringFromRow(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }

    private static function intFromRow(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
