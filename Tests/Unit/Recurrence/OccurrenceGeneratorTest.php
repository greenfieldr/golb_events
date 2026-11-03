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
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\GeneratedOccurrence;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceGenerator;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(OccurrenceGenerator::class)]
#[CoversClass(GeneratedOccurrence::class)]
#[CoversClass(EventSchedule::class)]
#[CoversClass(RecurrenceRuleBuilder::class)]
final class OccurrenceGeneratorTest extends UnitTestCase
{
    private OccurrenceGenerator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new OccurrenceGenerator(new RecurrenceRuleBuilder());
    }

    /**
     * @param array{allDay?: bool, interval?: int, weekdays?: string[], monthlyMode?: string, endMode?: string, until?: string, count?: int, exceptions?: string[]} $options
     */
    private function schedule(string $start, ?string $end, string $timezone, string $frequency = Event::FREQUENCY_NONE, array $options = []): EventSchedule
    {
        return new EventSchedule(
            new \DateTimeImmutable($start),
            $end === null ? null : new \DateTimeImmutable($end),
            $options['allDay'] ?? false,
            new \DateTimeZone($timezone),
            $frequency,
            $options['interval'] ?? 1,
            $options['weekdays'] ?? [],
            $options['monthlyMode'] ?? Event::MONTHLY_MODE_DAY_OF_MONTH,
            $options['endMode'] ?? Event::RECURRENCE_END_NEVER,
            isset($options['until']) ? new \DateTimeImmutable($options['until']) : null,
            $options['count'] ?? 0,
            $options['exceptions'] ?? [],
        );
    }

    /**
     * @param GeneratedOccurrence[] $occurrences
     * @return string[] start and end of every occurrence as ISO 8601 in the given time zone
     */
    private function format(array $occurrences, string $timezone): array
    {
        $zone = new \DateTimeZone($timezone);

        return array_map(
            static fn(GeneratedOccurrence $occurrence): string => (new \DateTimeImmutable('@' . $occurrence->start))->setTimezone($zone)->format('c')
                . ' - ' . (new \DateTimeImmutable('@' . $occurrence->end))->setTimezone($zone)->format('c'),
            $occurrences
        );
    }

    private function horizon(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
    }

    #[Test]
    public function nonRecurringEventHasExactlyOneOccurrenceEvenBeyondTheHorizon(): void
    {
        $schedule = $this->schedule('2026-05-04 19:30:00', '2026-05-04 21:00:00', 'America/New_York');

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-01-01'));

        self::assertSame(['2026-05-04T19:30:00-04:00 - 2026-05-04T21:00:00-04:00'], $this->format($occurrences, 'America/New_York'));
        self::assertSame(1777937400, $occurrences[0]->start, 'the start is the New York wall clock time as UTC timestamp');
        self::assertFalse($occurrences[0]->allDay);
    }

    #[Test]
    public function eventWithoutEndHasAZeroLengthOccurrence(): void
    {
        $occurrences = $this->subject->generate($this->schedule('2026-05-04 19:30:00', null, 'UTC'), $this->horizon('2027-01-01'));

        self::assertSame(['2026-05-04T19:30:00+00:00 - 2026-05-04T19:30:00+00:00'], $this->format($occurrences, 'UTC'));
    }

    #[Test]
    public function allDayEventSpansTheWholeDays(): void
    {
        $schedule = $this->schedule('2026-07-14 10:00:00', '2026-07-15 08:00:00', 'Europe/Berlin', Event::FREQUENCY_NONE, ['allDay' => true]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2027-01-01'));

        self::assertSame(['2026-07-14T00:00:00+02:00 - 2026-07-15T23:59:59+02:00'], $this->format($occurrences, 'Europe/Berlin'));
        self::assertTrue($occurrences[0]->allDay);
    }

    #[Test]
    public function weeklyRuleKeepsTheWallClockTimeAcrossDaylightSavingTransitions(): void
    {
        // Europe/Berlin switches to summer time on 2026-03-29
        $schedule = $this->schedule('2026-03-23 10:00:00', '2026-03-23 11:00:00', 'Europe/Berlin', Event::FREQUENCY_WEEKLY);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-04-07'));

        self::assertSame([
            '2026-03-23T10:00:00+01:00 - 2026-03-23T11:00:00+01:00',
            '2026-03-30T10:00:00+02:00 - 2026-03-30T11:00:00+02:00',
            '2026-04-06T10:00:00+02:00 - 2026-04-06T11:00:00+02:00',
        ], $this->format($occurrences, 'Europe/Berlin'));
        self::assertSame(7 * 86400 - 3600, $occurrences[1]->start - $occurrences[0]->start);
    }

    #[Test]
    public function weeklyRuleWithIntervalWeekdaysAndExceptions(): void
    {
        $schedule = $this->schedule('2026-06-01 18:00:00', '2026-06-01 20:00:00', 'Europe/Berlin', Event::FREQUENCY_WEEKLY, [
            'interval' => 2,
            'weekdays' => ['MO', 'WE'],
            'exceptions' => ['2026-06-15'],
        ]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-07-01'));

        self::assertSame([
            '2026-06-01T18:00:00+02:00 - 2026-06-01T20:00:00+02:00',
            '2026-06-03T18:00:00+02:00 - 2026-06-03T20:00:00+02:00',
            '2026-06-17T18:00:00+02:00 - 2026-06-17T20:00:00+02:00',
            '2026-06-29T18:00:00+02:00 - 2026-06-29T20:00:00+02:00',
        ], $this->format($occurrences, 'Europe/Berlin'));
    }

    #[Test]
    public function untilEndsTheRecurrenceAtTheEndOfTheUntilDay(): void
    {
        $schedule = $this->schedule('2026-05-04 19:30:00', null, 'Europe/Berlin', Event::FREQUENCY_DAILY, [
            'endMode' => Event::RECURRENCE_END_UNTIL,
            'until' => '2026-05-06',
        ]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2030-01-01'));

        self::assertCount(3, $occurrences);
        self::assertSame('2026-05-06T19:30:00+02:00', (new \DateTimeImmutable('@' . $occurrences[2]->start))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('c'));
    }

    #[Test]
    public function countLimitsTheNumberOfOccurrences(): void
    {
        $schedule = $this->schedule('2026-07-14 00:00:00', null, 'Europe/Berlin', Event::FREQUENCY_YEARLY, [
            'allDay' => true,
            'endMode' => Event::RECURRENCE_END_COUNT,
            'count' => 3,
        ]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2040-01-01'));

        self::assertSame([
            '2026-07-14T00:00:00+02:00 - 2026-07-14T23:59:59+02:00',
            '2027-07-14T00:00:00+02:00 - 2027-07-14T23:59:59+02:00',
            '2028-07-14T00:00:00+02:00 - 2028-07-14T23:59:59+02:00',
        ], $this->format($occurrences, 'Europe/Berlin'));
    }

    #[Test]
    public function horizonCapsNeverEndingRules(): void
    {
        $schedule = $this->schedule('2026-05-04 19:30:00', null, 'UTC', Event::FREQUENCY_DAILY);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-05-08 19:30:00'));

        self::assertCount(5, $occurrences, 'the horizon is inclusive');
    }

    #[Test]
    public function limitCapsRunawayRules(): void
    {
        $schedule = $this->schedule('2026-05-04 19:30:00', null, 'UTC', Event::FREQUENCY_DAILY);

        self::assertCount(10, $this->subject->generate($schedule, $this->horizon('2099-01-01'), 10));
    }

    #[Test]
    public function firstOccurrenceIsKeptWhenTheRuleStartsAfterTheHorizon(): void
    {
        $schedule = $this->schedule('2030-05-04 19:30:00', null, 'UTC', Event::FREQUENCY_WEEKLY);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-01-01'));

        self::assertSame(['2030-05-04T19:30:00+00:00 - 2030-05-04T19:30:00+00:00'], $this->format($occurrences, 'UTC'));
    }

    #[Test]
    public function monthlyOnTheSameWeekdayPosition(): void
    {
        // 2026-05-12 is the second Tuesday of May
        $schedule = $this->schedule('2026-05-12 09:00:00', '2026-05-12 10:00:00', 'Europe/Berlin', Event::FREQUENCY_MONTHLY, [
            'monthlyMode' => Event::MONTHLY_MODE_WEEKDAY,
        ]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-08-01'));

        self::assertSame([
            '2026-05-12T09:00:00+02:00 - 2026-05-12T10:00:00+02:00',
            '2026-06-09T09:00:00+02:00 - 2026-06-09T10:00:00+02:00',
            '2026-07-14T09:00:00+02:00 - 2026-07-14T10:00:00+02:00',
        ], $this->format($occurrences, 'Europe/Berlin'));
    }

    #[Test]
    public function monthlyOnTheSameDayOfMonthSkipsShortMonths(): void
    {
        $schedule = $this->schedule('2026-01-31 09:00:00', null, 'UTC', Event::FREQUENCY_MONTHLY);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2026-06-01'));

        self::assertSame([
            '2026-01-31T09:00:00+00:00 - 2026-01-31T09:00:00+00:00',
            '2026-03-31T09:00:00+00:00 - 2026-03-31T09:00:00+00:00',
            '2026-05-31T09:00:00+00:00 - 2026-05-31T09:00:00+00:00',
        ], $this->format($occurrences, 'UTC'));
    }

    #[Test]
    public function multiDayAllDayRecurrenceKeepsItsLength(): void
    {
        $schedule = $this->schedule('2026-05-01 00:00:00', '2026-05-03 00:00:00', 'UTC', Event::FREQUENCY_WEEKLY, [
            'allDay' => true,
            'endMode' => Event::RECURRENCE_END_COUNT,
            'count' => 2,
        ]);

        $occurrences = $this->subject->generate($schedule, $this->horizon('2027-01-01'));

        self::assertSame([
            '2026-05-01T00:00:00+00:00 - 2026-05-03T23:59:59+00:00',
            '2026-05-08T00:00:00+00:00 - 2026-05-10T23:59:59+00:00',
        ], $this->format($occurrences, 'UTC'));
    }
}
