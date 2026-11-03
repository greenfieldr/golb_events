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
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RecurrenceRuleBuilder::class)]
#[CoversClass(EventSchedule::class)]
final class RecurrenceRuleBuilderTest extends UnitTestCase
{
    /**
     * @param array{allDay?: bool, interval?: int, weekdays?: string[], monthlyMode?: string, endMode?: string, until?: string, count?: int, exceptions?: string[]} $options
     */
    private static function schedule(string $start, string $frequency, array $options = [], string $timezone = 'Europe/Berlin'): EventSchedule
    {
        return new EventSchedule(
            new \DateTimeImmutable($start),
            null,
            false,
            new \DateTimeZone($timezone),
            $frequency,
            $options['interval'] ?? 1,
            $options['weekdays'] ?? [],
            $options['monthlyMode'] ?? Event::MONTHLY_MODE_DAY_OF_MONTH,
            $options['endMode'] ?? Event::RECURRENCE_END_NEVER,
            isset($options['until']) ? new \DateTimeImmutable($options['until']) : null,
            $options['count'] ?? 0,
        );
    }

    #[Test]
    public function nonRecurringScheduleHasNoRule(): void
    {
        $builder = new RecurrenceRuleBuilder();
        $schedule = self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_NONE);

        self::assertNull($builder->build($schedule));
        self::assertNull($builder->buildString($schedule));
    }

    /**
     * @return iterable<string, array{EventSchedule, string}>
     */
    public static function ruleDataProvider(): iterable
    {
        yield 'daily' => [self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_DAILY), 'FREQ=DAILY;INTERVAL=1'];
        yield 'every third day' => [self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_DAILY, ['interval' => 3]), 'FREQ=DAILY;INTERVAL=3'];
        yield 'weekly on the weekday of the start' => [self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_WEEKLY), 'FREQ=WEEKLY;INTERVAL=1'];
        yield 'weekly on selected weekdays' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_WEEKLY, ['interval' => 2, 'weekdays' => ['MO', 'WE']]),
            'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE',
        ];
        yield 'monthly on the same day' => [self::schedule('2026-05-12 19:30:00', Event::FREQUENCY_MONTHLY), 'FREQ=MONTHLY;INTERVAL=1'];
        yield 'monthly on the second Tuesday' => [
            self::schedule('2026-05-12 19:30:00', Event::FREQUENCY_MONTHLY, ['monthlyMode' => Event::MONTHLY_MODE_WEEKDAY]),
            'FREQ=MONTHLY;INTERVAL=1;BYDAY=2TU',
        ];
        yield 'monthly on the first Friday' => [
            self::schedule('2026-05-01 19:30:00', Event::FREQUENCY_MONTHLY, ['monthlyMode' => Event::MONTHLY_MODE_WEEKDAY]),
            'FREQ=MONTHLY;INTERVAL=1;BYDAY=1FR',
        ];
        yield 'monthly on the fifth weekday becomes the last' => [
            self::schedule('2026-05-29 19:30:00', Event::FREQUENCY_MONTHLY, ['monthlyMode' => Event::MONTHLY_MODE_WEEKDAY]),
            'FREQ=MONTHLY;INTERVAL=1;BYDAY=-1FR',
        ];
        yield 'weekday selection is ignored for non weekly rules' => [
            self::schedule('2026-05-12 19:30:00', Event::FREQUENCY_MONTHLY, ['weekdays' => ['MO']]),
            'FREQ=MONTHLY;INTERVAL=1',
        ];
        yield 'yearly' => [self::schedule('2026-07-14 00:00:00', Event::FREQUENCY_YEARLY), 'FREQ=YEARLY;INTERVAL=1'];
        yield 'until the end of a day, written in UTC' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_WEEKLY, ['endMode' => Event::RECURRENCE_END_UNTIL, 'until' => '2026-12-31']),
            'FREQ=WEEKLY;INTERVAL=1;UNTIL=20261231T225959Z',
        ];
        yield 'until in another time zone' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_WEEKLY, ['endMode' => Event::RECURRENCE_END_UNTIL, 'until' => '2026-06-30'], 'America/New_York'),
            'FREQ=WEEKLY;INTERVAL=1;UNTIL=20260701T035959Z',
        ];
        yield 'count' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_DAILY, ['endMode' => Event::RECURRENCE_END_COUNT, 'count' => 5]),
            'FREQ=DAILY;INTERVAL=1;COUNT=5',
        ];
        yield 'count of zero means never ending' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_DAILY, ['endMode' => Event::RECURRENCE_END_COUNT, 'count' => 0]),
            'FREQ=DAILY;INTERVAL=1',
        ];
        yield 'until without a date means never ending' => [
            self::schedule('2026-05-04 19:30:00', Event::FREQUENCY_DAILY, ['endMode' => Event::RECURRENCE_END_UNTIL]),
            'FREQ=DAILY;INTERVAL=1',
        ];
    }

    #[Test]
    #[DataProvider('ruleDataProvider')]
    public function ruleStringIsBuiltFromTheSchedule(EventSchedule $schedule, string $expected): void
    {
        self::assertSame($expected, (new RecurrenceRuleBuilder())->buildString($schedule));
    }

    #[Test]
    public function untilPartIsTheLastSecondOfTheDayInTheScheduleTimezone(): void
    {
        $schedule = self::schedule(
            '2026-05-04 19:30:00',
            Event::FREQUENCY_WEEKLY,
            ['endMode' => Event::RECURRENCE_END_UNTIL, 'until' => '2026-12-31'],
            'America/New_York'
        );

        $parts = (new RecurrenceRuleBuilder())->build($schedule);

        self::assertIsArray($parts);
        self::assertInstanceOf(\DateTimeImmutable::class, $parts['UNTIL']);
        self::assertSame('2026-12-31T23:59:59-05:00', $parts['UNTIL']->format('c'));
    }
}
