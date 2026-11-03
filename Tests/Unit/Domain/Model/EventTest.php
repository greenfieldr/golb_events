<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Domain\Model;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Event::class)]
final class EventTest extends UnitTestCase
{
    #[Test]
    public function timezoneFallsBackToTheServerTimezoneWithoutLocation(): void
    {
        $event = new Event();

        self::assertSame(date_default_timezone_get(), $event->getTimezone()->getName());
    }

    #[Test]
    public function timezoneFallsBackToTheServerTimezoneForLocationWithoutTimezone(): void
    {
        $event = new Event();
        $event->setLocation(new Location());

        self::assertSame(date_default_timezone_get(), $event->getTimezone()->getName());
    }

    #[Test]
    public function timezoneComesFromTheLocation(): void
    {
        $location = new Location();
        $location->setTimezone('America/New_York');
        $event = new Event();
        $event->setLocation($location);

        self::assertSame('America/New_York', $event->getTimezone()->getName());
    }

    #[Test]
    public function locationAndOrganizerNamesPreferTheRecordsOverFreeText(): void
    {
        $event = new Event();
        $event->setLocationText('Town hall');
        $event->setOrganizerText('Volunteers');

        self::assertSame('Town hall', $event->getLocationName());
        self::assertSame('Volunteers', $event->getOrganizerName());

        $location = new Location();
        $location->setName('Carnegie Hall');
        $organizer = new Organizer();
        $organizer->setName('Golb Society');
        $event->setLocation($location);
        $event->setOrganizer($organizer);

        self::assertSame('Carnegie Hall', $event->getLocationName());
        self::assertSame('Golb Society', $event->getOrganizerName());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function frequencyDataProvider(): iterable
    {
        yield 'none' => [Event::FREQUENCY_NONE, false];
        yield 'empty' => ['', false];
        yield 'unknown' => ['hourly', false];
        yield 'daily' => [Event::FREQUENCY_DAILY, true];
        yield 'weekly' => [Event::FREQUENCY_WEEKLY, true];
        yield 'monthly' => [Event::FREQUENCY_MONTHLY, true];
        yield 'yearly' => [Event::FREQUENCY_YEARLY, true];
    }

    #[Test]
    #[DataProvider('frequencyDataProvider')]
    public function isRecurringDependsOnTheFrequency(string $frequency, bool $expected): void
    {
        $event = new Event();
        $event->setRecurrenceFrequency($frequency);

        self::assertSame($expected, $event->isRecurring());
    }

    #[Test]
    public function recurrenceIntervalIsAtLeastOne(): void
    {
        $event = new Event();

        self::assertSame(1, $event->getRecurrenceInterval());

        $event->setRecurrenceInterval(0);
        self::assertSame(1, $event->getRecurrenceInterval());

        $event->setRecurrenceInterval(3);
        self::assertSame(3, $event->getRecurrenceInterval());
    }

    /**
     * @return iterable<string, array{string, string[]}>
     */
    public static function weekdayDataProvider(): iterable
    {
        yield 'empty' => ['', []];
        yield 'single' => ['FR', ['FR']];
        yield 'calendar order is restored' => ['WE,MO', ['MO', 'WE']];
        yield 'whitespace and case are tolerated' => [' tu , th ', ['TU', 'TH']];
        yield 'unknown codes are dropped' => ['MO,XX,SU', ['MO', 'SU']];
        yield 'duplicates are dropped' => ['MO,MO', ['MO']];
    }

    /**
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('weekdayDataProvider')]
    public function recurrenceWeekdayListIsNormalised(string $weekdays, array $expected): void
    {
        $event = new Event();
        $event->setRecurrenceWeekdays($weekdays);

        self::assertSame($expected, $event->getRecurrenceWeekdayList());
    }

    #[Test]
    public function exceptionsAndOccurrencesCanBeAddedAndRemoved(): void
    {
        $event = new Event();
        $exception = new RecurrenceException();
        $occurrence = new Occurrence();

        $event->addException($exception);
        $event->addOccurrence($occurrence);
        self::assertCount(1, $event->getExceptions());
        self::assertCount(1, $event->getOccurrences());

        $event->removeException($exception);
        $event->removeOccurrence($occurrence);
        self::assertCount(0, $event->getExceptions());
        self::assertCount(0, $event->getOccurrences());
    }
}
