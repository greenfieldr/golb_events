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

use RRule\RRule;
use RRule\RSet;

/**
 * Expands the schedule of an event into its occurrences.
 *
 * The wall clock start is interpreted in the time zone of the schedule, the
 * recurrence rule is expanded in that zone (so an event at 19:00 stays at
 * 19:00 across daylight saving transitions) and every occurrence is
 * returned as UTC timestamps. The duration of the first occurrence applies
 * to all of them.
 */
final readonly class OccurrenceGenerator
{
    /**
     * Safety net against runaway rules.
     */
    public const MAX_OCCURRENCES = 1000;

    public function __construct(private RecurrenceRuleBuilder $ruleBuilder) {}

    /**
     * @param \DateTimeImmutable $horizon no occurrence starts after this point in time
     * @return list<GeneratedOccurrence>
     */
    public function generate(EventSchedule $schedule, \DateTimeImmutable $horizon, int $limit = self::MAX_OCCURRENCES): array
    {
        $start = $schedule->getStartInTimezone();
        $end = $schedule->getEndInTimezone();
        $parts = $this->ruleBuilder->build($schedule);

        if ($parts === null) {
            return [$this->occurrence($schedule, $start, $end)];
        }

        $ruleSet = new RSet();
        $ruleSet->addRRule(new RRule($parts + ['DTSTART' => $start]));
        foreach ($schedule->exceptionDates as $exceptionDate) {
            $ruleSet->addExDate(new \DateTimeImmutable($exceptionDate . ' ' . $start->format('H:i:s'), $schedule->timezone));
        }

        $dates = $ruleSet->getOccurrencesBetween($start, $horizon, max(1, $limit));
        if ($dates === []) {
            // Keep at least the first date so the event is listed at all
            $dates = $ruleSet->getOccurrences(1);
        }

        $duration = $end->getTimestamp() - $start->getTimestamp();
        $occurrences = [];
        foreach ($dates as $date) {
            $occurrenceStart = \DateTimeImmutable::createFromInterface($date);
            $occurrenceEnd = $schedule->allDay
                ? $this->endOfDay($occurrenceStart, $end, $start)
                : $occurrenceStart->modify('+' . $duration . ' seconds');
            $occurrences[] = $this->occurrence($schedule, $occurrenceStart, $occurrenceEnd);
        }

        return $occurrences;
    }

    private function occurrence(EventSchedule $schedule, \DateTimeImmutable $start, \DateTimeImmutable $end): GeneratedOccurrence
    {
        return new GeneratedOccurrence($start->getTimestamp(), max($start->getTimestamp(), $end->getTimestamp()), $schedule->allDay);
    }

    /**
     * All day occurrences span as many calendar days as the first one and end
     * at the last second of their last day.
     */
    private function endOfDay(\DateTimeImmutable $occurrenceStart, \DateTimeImmutable $firstEnd, \DateTimeImmutable $firstStart): \DateTimeImmutable
    {
        $days = (int)$firstStart->setTime(0, 0)->diff($firstEnd->setTime(0, 0))->format('%a');

        return $occurrenceStart->modify('+' . $days . ' days')->setTime(23, 59, 59);
    }
}
