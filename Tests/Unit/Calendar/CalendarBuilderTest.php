<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Calendar;

use Greenfieldr\GolbEvents\Calendar\Calendar;
use Greenfieldr\GolbEvents\Calendar\CalendarBuilder;
use Greenfieldr\GolbEvents\Calendar\CalendarDay;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CalendarBuilder::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(CalendarDay::class)]
final class CalendarBuilderTest extends UnitTestCase
{
    private CalendarBuilder $subject;

    private \DateTimeZone $berlin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new CalendarBuilder();
        $this->berlin = new \DateTimeZone('Europe/Berlin');
    }

    private function occurrence(string $start, string $end, string $timezone = 'UTC', bool $allDay = false): Occurrence
    {
        $occurrence = new Occurrence();
        $occurrence->setStart(new \DateTimeImmutable($start, new \DateTimeZone($timezone)));
        $occurrence->setEnd(new \DateTimeImmutable($end, new \DateTimeZone($timezone)));
        $occurrence->setAllDay($allDay);

        return $occurrence;
    }

    /**
     * @return iterable<string, array{int, int, int, string, string}>
     */
    public static function gridRangeDataProvider(): iterable
    {
        // 2026-06-01 is a Monday, 2026-06-30 a Tuesday
        yield 'June 2026, weeks starting Monday' => [2026, 6, 1, '2026-06-01 00:00:00', '2026-07-05 23:59:59'];
        yield 'June 2026, weeks starting Sunday' => [2026, 6, 7, '2026-05-31 00:00:00', '2026-07-04 23:59:59'];
        // 2026-02-01 is a Sunday, 28 days
        yield 'February 2026, weeks starting Monday' => [2026, 2, 1, '2026-01-26 00:00:00', '2026-03-01 23:59:59'];
        yield 'February 2026, weeks starting Sunday' => [2026, 2, 7, '2026-02-01 00:00:00', '2026-02-28 23:59:59'];
        yield 'December 2026 crosses the year' => [2026, 12, 1, '2026-11-30 00:00:00', '2027-01-03 23:59:59'];
        yield 'invalid first day of week falls back to Monday' => [2026, 6, 0, '2026-06-01 00:00:00', '2026-07-05 23:59:59'];
    }

    #[Test]
    #[DataProvider('gridRangeDataProvider')]
    public function gridRangeCoversWholeWeeks(int $year, int $month, int $firstDayOfWeek, string $expectedStart, string $expectedEnd): void
    {
        [$start, $end] = $this->subject->gridRange($year, $month, $this->berlin, $firstDayOfWeek);

        self::assertSame($expectedStart, $start->format('Y-m-d H:i:s'));
        self::assertSame($expectedEnd, $end->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Berlin', $start->getTimezone()->getName());
    }

    #[Test]
    public function buildsWeeksOfSevenDaysWithMonthAndTodayFlags(): void
    {
        $calendar = $this->subject->build(2026, 6, [], $this->berlin, 1, new \DateTimeImmutable('2026-06-15 23:30:00', new \DateTimeZone('UTC')));

        self::assertSame(2026, $calendar->getYear());
        self::assertSame(6, $calendar->getMonth());
        self::assertSame('2026-06-01', $calendar->getFirstDay()->format('Y-m-d'));
        self::assertCount(5, $calendar->getWeeks());
        foreach ($calendar->getWeeks() as $week) {
            self::assertCount(7, $week);
        }
        self::assertSame('2026-06-01', $calendar->getWeekdays()[0]->getKey());
        self::assertSame('2026-06-07', $calendar->getWeekdays()[6]->getKey());

        $lastWeek = $calendar->getWeeks()[4];
        self::assertTrue($lastWeek[1]->isInMonth(), '2026-06-30');
        self::assertFalse($lastWeek[2]->isInMonth(), '2026-07-01');
        self::assertSame('2026-07-05', $lastWeek[6]->getKey());

        // 23:30 UTC on the 15th is already the 16th in Berlin
        self::assertTrue($calendar->getWeeks()[2][1]->isToday(), '2026-06-16');
        self::assertFalse($calendar->getWeeks()[2][0]->isToday(), '2026-06-15');
        self::assertFalse($calendar->hasEvents());
    }

    #[Test]
    public function navigationWrapsAroundTheYear(): void
    {
        $january = $this->subject->build(2026, 1, [], $this->berlin);
        $december = $this->subject->build(2026, 12, [], $this->berlin);

        self::assertSame([2025, 12], [$january->getPreviousYear(), $january->getPreviousMonth()]);
        self::assertSame([2026, 2], [$january->getNextYear(), $january->getNextMonth()]);
        self::assertSame([2026, 11], [$december->getPreviousYear(), $december->getPreviousMonth()]);
        self::assertSame([2027, 1], [$december->getNextYear(), $december->getNextMonth()]);
    }

    #[Test]
    public function occurrencesAreFiledUnderTheirDaysInTheCalendarTimezone(): void
    {
        $evening = $this->occurrence('2026-06-10 22:30:00', '2026-06-10 23:30:00'); // 00:30 - 01:30 Berlin on the 11th
        $multiDay = $this->occurrence('2026-06-20 00:00:00', '2026-06-22 23:59:59', 'Europe/Berlin', true);
        $beforeGrid = $this->occurrence('2026-05-01 10:00:00', '2026-05-01 11:00:00');
        $acrossGridStart = $this->occurrence('2026-05-30 10:00:00', '2026-06-02 11:00:00', 'Europe/Berlin');

        $calendar = $this->subject->build(2026, 6, [$evening, $multiDay, $beforeGrid, $acrossGridStart], $this->berlin);

        $days = [];
        foreach ($calendar->getWeeks() as $week) {
            foreach ($week as $day) {
                if ($day->hasOccurrences()) {
                    $days[$day->getKey()] = count($day->getOccurrences());
                }
            }
        }
        self::assertSame([
            '2026-06-01' => 1,
            '2026-06-02' => 1,
            '2026-06-11' => 1,
            '2026-06-20' => 1,
            '2026-06-21' => 1,
            '2026-06-22' => 1,
        ], $days);
        self::assertTrue($calendar->hasEvents());
        self::assertSame($evening, $calendar->getWeeks()[1][3]->getOccurrences()[0]);
    }

    #[Test]
    public function occurrenceWithoutStartIsIgnored(): void
    {
        $calendar = $this->subject->build(2026, 6, [new Occurrence()], $this->berlin);

        self::assertFalse($calendar->hasEvents());
    }
}
