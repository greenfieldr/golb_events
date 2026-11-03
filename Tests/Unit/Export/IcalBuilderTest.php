<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Export;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;
use Greenfieldr\GolbEvents\Export\IcalBuilder;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(IcalBuilder::class)]
#[CoversClass(EventSchedule::class)]
#[CoversClass(RecurrenceRuleBuilder::class)]
final class IcalBuilderTest extends UnitTestCase
{
    private IcalBuilder $subject;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new IcalBuilder(new RecurrenceRuleBuilder());
        $this->now = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('UTC'));
    }

    private function event(int $uid, string $title, ?string $start, ?string $end = null): Event
    {
        $event = new Event();
        $event->_setProperty('uid', $uid);
        $event->setTitle($title);
        $event->setStart($start === null ? null : new \DateTimeImmutable($start));
        $event->setEnd($end === null ? null : new \DateTimeImmutable($end));

        return $event;
    }

    private function location(string $timezone): Location
    {
        $location = new Location();
        $location->setName('Carnegie Hall');
        $location->setStreet('881 7th Ave');
        $location->setZip('10019');
        $location->setCity('New York');
        $location->setTimezone($timezone);
        $location->setLatitude(40.765);
        $location->setLongitude(-73.98);

        return $location;
    }

    /**
     * @return string[] the unfolded content lines
     */
    private function lines(string $ical): array
    {
        self::assertStringEndsWith("\r\n", $ical);

        return explode("\r\n", rtrim(str_replace("\r\n ", '', $ical), "\r\n"));
    }

    #[Test]
    public function eventWithoutStartHasNoCalendar(): void
    {
        self::assertNull($this->subject->build($this->event(1, 'No date', null), '', 'example.org', $this->now));
    }

    #[Test]
    public function timedEventInTheServerTimezone(): void
    {
        $event = $this->event(7, 'Conference', '2026-05-04 19:30:00', '2026-05-04 21:00:00');
        $event->setAbstract("First line\nsecond, with; special \\ chars");
        $event->setLocationText('Town hall');
        $event->setOrganizerText('Volunteers');
        $event->setTstamp(1700000000);

        $lines = $this->lines((string)$this->subject->build($event, 'https://example.org/events/conference', 'example.org', $this->now));
        $serverZone = date_default_timezone_get();
        $dateTimeLine = static fn(string $name, string $value): string => $serverZone === 'UTC'
            ? $name . ':' . $value . 'Z'
            : $name . ';TZID=' . $serverZone . ':' . $value;

        self::assertSame([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Golb Events//TYPO3//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:golb-event-7@example.org',
            'DTSTAMP:20260101T120000Z',
            $dateTimeLine('DTSTART', '20260504T193000'),
            $dateTimeLine('DTEND', '20260504T210000'),
            'SUMMARY:Conference',
            'DESCRIPTION:First line\nsecond\, with\; special \\\\ chars',
            'LOCATION:Town hall',
            'URL:https://example.org/events/conference',
            'STATUS:CONFIRMED',
            'LAST-MODIFIED:20231114T221320Z',
            'END:VEVENT',
            'END:VCALENDAR',
        ], $lines);
    }

    #[Test]
    public function eventAtALocationUsesItsTimezoneAddressAndCoordinates(): void
    {
        $event = $this->event(8, 'Concert', '2026-05-04 19:30:00', '2026-05-04 21:00:00');
        $event->setLocation($this->location('America/New_York'));
        $organizer = new Organizer();
        $organizer->setName('Golb "Society"');
        $organizer->setEmail('mail@example.org');
        $event->setOrganizer($organizer);
        $event->setCancelled(true);

        $lines = $this->lines((string)$this->subject->build($event, '', 'example.org', $this->now));

        self::assertContains('DTSTART;TZID=America/New_York:20260504T193000', $lines);
        self::assertContains('DTEND;TZID=America/New_York:20260504T210000', $lines);
        self::assertContains('LOCATION:Carnegie Hall\, 881 7th Ave\, 10019 New York', $lines);
        self::assertContains('GEO:40.765;-73.98', $lines);
        self::assertContains('ORGANIZER;CN="Golb Society":mailto:mail@example.org', $lines);
        self::assertContains('STATUS:CANCELLED', $lines);
        self::assertNotContains('URL:', $lines);
    }

    #[Test]
    public function allDayEventEndsExclusivelyOnTheNextDay(): void
    {
        $event = $this->event(9, 'Festival', '2026-07-01 10:00:00', '2026-07-03 08:00:00');
        $event->setAllDay(true);

        $lines = $this->lines((string)$this->subject->build($event, '', 'example.org', $this->now));

        self::assertContains('DTSTART;VALUE=DATE:20260701', $lines);
        self::assertContains('DTEND;VALUE=DATE:20260704', $lines);
    }

    #[Test]
    public function recurringEventCarriesRuleAndExcludedDates(): void
    {
        $event = $this->event(10, 'Meetup', '2026-06-01 18:00:00', '2026-06-01 20:00:00');
        $event->setLocation($this->location('Europe/Berlin'));
        $event->setRecurrenceFrequency(Event::FREQUENCY_WEEKLY);
        $event->setRecurrenceInterval(2);
        $event->setRecurrenceWeekdays('MO,WE');
        $event->setRecurrenceEnd(Event::RECURRENCE_END_UNTIL);
        $event->setRecurrenceUntil(new \DateTimeImmutable('2026-12-31'));
        foreach (['2026-06-15', '2026-06-17'] as $date) {
            $exception = new RecurrenceException();
            $exception->setDate(new \DateTimeImmutable($date));
            $event->addException($exception);
        }

        $lines = $this->lines((string)$this->subject->build($event, '', 'example.org', $this->now));

        self::assertContains('RRULE:FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;UNTIL=20261231T225959Z', $lines);
        self::assertContains('EXDATE;TZID=Europe/Berlin:20260615T180000,20260617T180000', $lines);
    }

    #[Test]
    public function allDayRecurrenceExcludesWholeDays(): void
    {
        $event = $this->event(11, 'Holiday', '2026-07-14 00:00:00');
        $event->setAllDay(true);
        $event->setRecurrenceFrequency(Event::FREQUENCY_YEARLY);
        $exception = new RecurrenceException();
        $exception->setDate(new \DateTimeImmutable('2027-07-14'));
        $event->addException($exception);

        $lines = $this->lines((string)$this->subject->build($event, '', 'example.org', $this->now));

        self::assertContains('RRULE:FREQ=YEARLY;INTERVAL=1', $lines);
        self::assertContains('EXDATE;VALUE=DATE:20270714', $lines);
    }

    #[Test]
    public function longLinesAreFoldedAt75Octets(): void
    {
        $event = $this->event(12, str_repeat('Ünïcödé title ', 10), '2026-05-04 19:30:00');

        $ical = (string)$this->subject->build($event, '', 'example.org', $this->now);

        foreach (explode("\r\n", $ical) as $line) {
            self::assertLessThanOrEqual(75, strlen($line), $line);
        }
        self::assertStringContainsString("\r\n ", $ical, 'at least one folded line');
        self::assertContains('SUMMARY:' . str_repeat('Ünïcödé title ', 10), $this->lines($ical), 'folding keeps multibyte characters intact');
    }
}
