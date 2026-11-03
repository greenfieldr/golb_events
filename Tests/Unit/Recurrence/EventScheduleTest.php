<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Recurrence;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(EventSchedule::class)]
final class EventScheduleTest extends UnitTestCase
{
    #[Test]
    public function fromEventWithoutStartIsNull(): void
    {
        self::assertNull(EventSchedule::fromEvent(new Event()));
    }

    #[Test]
    public function fromEventCopiesTheRuleAndTheExceptions(): void
    {
        $location = new Location();
        $location->setTimezone('America/New_York');
        $exception = new RecurrenceException();
        $exception->setDate(new \DateTimeImmutable('2026-06-08'));

        $event = new Event();
        $event->setStart(new \DateTimeImmutable('2026-06-01 18:00:00'));
        $event->setEnd(new \DateTimeImmutable('2026-06-01 20:00:00'));
        $event->setLocation($location);
        $event->setRecurrenceFrequency(Event::FREQUENCY_WEEKLY);
        $event->setRecurrenceInterval(2);
        $event->setRecurrenceWeekdays('WE,MO');
        $event->setRecurrenceEnd(Event::RECURRENCE_END_COUNT);
        $event->setRecurrenceCount(4);
        $event->addException($exception);

        $schedule = EventSchedule::fromEvent($event);

        self::assertInstanceOf(EventSchedule::class, $schedule);
        self::assertSame('2026-06-01 18:00:00', $schedule->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-01 20:00:00', $schedule->end?->format('Y-m-d H:i:s'));
        self::assertFalse($schedule->allDay);
        self::assertSame('America/New_York', $schedule->timezone->getName());
        self::assertSame(Event::FREQUENCY_WEEKLY, $schedule->frequency);
        self::assertSame(2, $schedule->interval);
        self::assertSame(['MO', 'WE'], $schedule->weekdays);
        self::assertSame(Event::RECURRENCE_END_COUNT, $schedule->endMode);
        self::assertSame(4, $schedule->count);
        self::assertSame(['2026-06-08'], $schedule->exceptionDates);
        self::assertTrue($schedule->isRecurring());
    }

    #[Test]
    public function fromRecordWithoutStartIsNull(): void
    {
        self::assertNull(EventSchedule::fromRecord(['tx_golbevents_start' => null]));
        self::assertNull(EventSchedule::fromRecord(['tx_golbevents_start' => '']));
        self::assertNull(EventSchedule::fromRecord(['tx_golbevents_start' => '0000-00-00 00:00:00']));
        self::assertNull(EventSchedule::fromRecord([]));
    }

    #[Test]
    public function fromRecordReadsDatabaseValues(): void
    {
        $schedule = EventSchedule::fromRecord([
            'tx_golbevents_start' => '2026-07-14 00:00:00',
            'tx_golbevents_end' => null,
            'tx_golbevents_all_day' => 1,
            'tx_golbevents_recurrence_frequency' => 'yearly',
            'tx_golbevents_recurrence_interval' => 0,
            'tx_golbevents_recurrence_weekdays' => 'xx, fr ,MO',
            'tx_golbevents_recurrence_monthly_mode' => 'weekday',
            'tx_golbevents_recurrence_end' => 'until',
            'tx_golbevents_recurrence_until' => '2030-01-01',
            'tx_golbevents_recurrence_count' => '3',
        ], 'Europe/Berlin', ['2027-07-14']);

        self::assertInstanceOf(EventSchedule::class, $schedule);
        self::assertSame('2026-07-14 00:00:00', $schedule->start->format('Y-m-d H:i:s'));
        self::assertNull($schedule->end);
        self::assertTrue($schedule->allDay);
        self::assertSame('Europe/Berlin', $schedule->timezone->getName());
        self::assertSame(Event::FREQUENCY_YEARLY, $schedule->frequency);
        self::assertSame(1, $schedule->interval, 'interval is at least 1');
        self::assertSame(['MO', 'FR'], $schedule->weekdays);
        self::assertSame(Event::MONTHLY_MODE_WEEKDAY, $schedule->monthlyMode);
        self::assertSame(Event::RECURRENCE_END_UNTIL, $schedule->endMode);
        self::assertSame('2030-01-01 00:00:00', $schedule->until?->format('Y-m-d H:i:s'));
        self::assertSame(3, $schedule->count);
        self::assertSame(['2027-07-14'], $schedule->exceptionDates);
    }

    #[Test]
    public function fromRecordDefaultsToTheServerTimezoneAndNoRecurrence(): void
    {
        $schedule = EventSchedule::fromRecord(['tx_golbevents_start' => '2026-05-04 19:30:00']);

        self::assertInstanceOf(EventSchedule::class, $schedule);
        self::assertSame(date_default_timezone_get(), $schedule->timezone->getName());
        self::assertSame(Event::FREQUENCY_NONE, $schedule->frequency);
        self::assertFalse($schedule->isRecurring());
        self::assertSame([], $schedule->weekdays);
    }

    #[Test]
    public function startAndEndAreInterpretedInTheTimezoneOfTheSchedule(): void
    {
        $schedule = new EventSchedule(
            new \DateTimeImmutable('2026-05-04 19:30:00'),
            new \DateTimeImmutable('2026-05-04 21:00:00'),
            false,
            new \DateTimeZone('America/New_York')
        );

        self::assertSame('2026-05-04T19:30:00-04:00', $schedule->getStartInTimezone()->format('c'));
        self::assertSame('2026-05-04T21:00:00-04:00', $schedule->getEndInTimezone()->format('c'));
    }

    #[Test]
    public function allDayEventsSpanWholeDays(): void
    {
        $schedule = new EventSchedule(
            new \DateTimeImmutable('2026-07-14 10:00:00'),
            new \DateTimeImmutable('2026-07-15 08:00:00'),
            true,
            new \DateTimeZone('Europe/Berlin')
        );

        self::assertSame('2026-07-14T00:00:00+02:00', $schedule->getStartInTimezone()->format('c'));
        self::assertSame('2026-07-15T23:59:59+02:00', $schedule->getEndInTimezone()->format('c'));
    }

    #[Test]
    public function endBeforeStartOrMissingEndFallsBackToTheStart(): void
    {
        $timezone = new \DateTimeZone('UTC');
        $withoutEnd = new EventSchedule(new \DateTimeImmutable('2026-05-04 19:30:00'), null, false, $timezone);
        $endBeforeStart = new EventSchedule(
            new \DateTimeImmutable('2026-05-04 19:30:00'),
            new \DateTimeImmutable('2026-05-04 18:00:00'),
            false,
            $timezone
        );

        self::assertSame('2026-05-04T19:30:00+00:00', $withoutEnd->getEndInTimezone()->format('c'));
        self::assertSame('2026-05-04T19:30:00+00:00', $endBeforeStart->getEndInTimezone()->format('c'));
    }
}
