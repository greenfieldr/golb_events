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

/**
 * Turns the structured recurrence fields of an event into an RFC 5545
 * recurrence rule.
 */
final class RecurrenceRuleBuilder
{
    /**
     * Returns the rule parts (FREQ, INTERVAL, BYDAY, UNTIL, COUNT) of the
     * schedule, or null if the event does not recur. UNTIL is the last second
     * of the until day in the time zone of the schedule.
     *
     * @return array<string, string|int|\DateTimeImmutable>|null
     */
    public function build(EventSchedule $schedule): ?array
    {
        if (!$schedule->isRecurring()) {
            return null;
        }

        $parts = [
            'FREQ' => strtoupper($schedule->frequency),
            'INTERVAL' => max(1, $schedule->interval),
        ];

        if ($schedule->frequency === Event::FREQUENCY_WEEKLY && $schedule->weekdays !== []) {
            $parts['BYDAY'] = implode(',', $schedule->weekdays);
        }
        if ($schedule->frequency === Event::FREQUENCY_MONTHLY && $schedule->monthlyMode === Event::MONTHLY_MODE_WEEKDAY) {
            $parts['BYDAY'] = $this->weekdayPositionOf($schedule->start);
        }

        if ($schedule->endMode === Event::RECURRENCE_END_UNTIL && $schedule->until instanceof \DateTimeImmutable) {
            $parts['UNTIL'] = $schedule->wallClockInTimezone($schedule->until->setTime(23, 59, 59));
        } elseif ($schedule->endMode === Event::RECURRENCE_END_COUNT && $schedule->count > 0) {
            $parts['COUNT'] = $schedule->count;
        }

        return $parts;
    }

    /**
     * Returns the rule as RRULE property value (without the "RRULE:" prefix),
     * e.g. for iCal export. UNTIL is written in UTC as the RFC requires for
     * rules with a time zone aware DTSTART.
     */
    public function buildString(EventSchedule $schedule): ?string
    {
        $parts = $this->build($schedule);
        if ($parts === null) {
            return null;
        }
        $segments = [];
        foreach ($parts as $name => $value) {
            if ($value instanceof \DateTimeImmutable) {
                $value = $value->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
            }
            $segments[] = $name . '=' . $value;
        }

        return implode(';', $segments);
    }

    /**
     * The BYDAY value of "the same weekday" monthly rules: the weekday of the
     * start with its position in the month, e.g. "2TU" for the second Tuesday.
     * The fifth position is expressed as the last one, because most months
     * do not have a fifth.
     */
    private function weekdayPositionOf(\DateTimeImmutable $date): string
    {
        $position = intdiv((int)$date->format('j') - 1, 7) + 1;
        if ($position === 5) {
            $position = -1;
        }

        return $position . Event::WEEKDAYS[(int)$date->format('N') - 1];
    }
}
