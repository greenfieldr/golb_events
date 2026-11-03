<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Domain\Repository;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;
use Greenfieldr\GolbEvents\Domain\Repository\EventRepository;
use Greenfieldr\GolbEvents\Domain\Repository\LocationRepository;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEvents\Domain\Repository\OrganizerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies the Extbase mapping of the event page and the related records.
 */
#[CoversClass(EventRepository::class)]
#[CoversClass(LocationRepository::class)]
#[CoversClass(OrganizerRepository::class)]
#[CoversClass(OccurrenceRepository::class)]
#[CoversClass(Event::class)]
#[CoversClass(Location::class)]
#[CoversClass(Organizer::class)]
#[CoversClass(RecurrenceException::class)]
#[CoversClass(Occurrence::class)]
final class EventRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    private EventRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/EventSite.csv');
        $this->subject = $this->get(EventRepository::class);
    }

    private function findEvent(int $uid): Event
    {
        $event = $this->subject->findByUid($uid);
        self::assertInstanceOf(Event::class, $event);

        return $event;
    }

    #[Test]
    public function findAllReturnsOnlyPagesOfTheEventDoktype(): void
    {
        $uids = array_map(
            static fn(Event $event): int => (int)$event->getUid(),
            $this->subject->findAll()->toArray()
        );
        sort($uids);

        self::assertSame([3, 4, 5], $uids);
    }

    #[Test]
    public function datesAreMappedToWallClockTimesInTheServerTimezone(): void
    {
        $event = $this->findEvent(3);

        self::assertSame('2026-05-04 19:30:00', $event->getStart()?->format('Y-m-d H:i:s'));
        self::assertSame(date_default_timezone_get(), $event->getStart()->getTimezone()->getName());
        self::assertSame('2026-05-04 21:00:00', $event->getEnd()?->format('Y-m-d H:i:s'));
        self::assertFalse($event->isAllDay());
        self::assertFalse($event->isCancelled());
        self::assertSame('https://example.org/tickets', $event->getLink());
        self::assertSame(5000, $event->getPublishDate());
    }

    #[Test]
    public function missingEndDateIsNull(): void
    {
        $event = $this->findEvent(4);

        self::assertNull($event->getEnd());
        self::assertTrue($event->isCancelled());
    }

    #[Test]
    public function locationAndOrganizerRecordsAreMapped(): void
    {
        $event = $this->findEvent(3);

        $location = $event->getLocation();
        self::assertInstanceOf(Location::class, $location);
        self::assertSame('Carnegie Hall', $location->getName());
        self::assertSame('New York', $location->getCity());
        self::assertSame('US', $location->getCountry());
        self::assertSame('America/New_York', $location->getTimezone());
        self::assertTrue($location->hasCoordinates());
        self::assertEqualsWithDelta(40.765, $location->getLatitude(), 0.0001);
        self::assertEqualsWithDelta(-73.98, $location->getLongitude(), 0.0001);
        self::assertSame('Carnegie Hall', $event->getLocationName());
        self::assertSame('America/New_York', $event->getTimezone()->getName());

        $organizer = $event->getOrganizer();
        self::assertInstanceOf(Organizer::class, $organizer);
        self::assertSame('Golb Society', $organizer->getName());
        self::assertSame('mail@example.org', $organizer->getEmail());
        self::assertSame('Golb Society', $event->getOrganizerName());
    }

    #[Test]
    public function freeTextLocationAndOrganizerAreUsedWithoutRecords(): void
    {
        $event = $this->findEvent(4);

        self::assertNull($event->getLocation());
        self::assertSame('Town hall', $event->getLocationName());
        self::assertNull($event->getOrganizer());
        self::assertSame('Volunteers', $event->getOrganizerName());
        self::assertSame(date_default_timezone_get(), $event->getTimezone()->getName());
    }

    #[Test]
    public function locationWithoutCoordinatesAndTimezone(): void
    {
        $location = $this->get(LocationRepository::class)->findByUid(2);

        self::assertInstanceOf(Location::class, $location);
        self::assertFalse($location->hasCoordinates());
        self::assertNull($location->getLatitude());
        self::assertSame('', $location->getTimezone());
    }

    #[Test]
    public function recurrenceRuleAndExceptionsAreMapped(): void
    {
        $event = $this->findEvent(4);

        self::assertTrue($event->isRecurring());
        self::assertSame(Event::FREQUENCY_WEEKLY, $event->getRecurrenceFrequency());
        self::assertSame(2, $event->getRecurrenceInterval());
        self::assertSame(['MO', 'WE'], $event->getRecurrenceWeekdayList());
        self::assertSame(Event::RECURRENCE_END_UNTIL, $event->getRecurrenceEnd());
        self::assertSame('2026-12-31', $event->getRecurrenceUntil()?->format('Y-m-d'));

        $dates = array_map(
            static fn(RecurrenceException $exception): string => (string)$exception->getDate()?->format('Y-m-d'),
            $event->getExceptions()->toArray()
        );
        self::assertSame(['2026-06-08', '2026-06-10'], $dates);
    }

    #[Test]
    public function countBasedRecurrenceOfAnAllDayEvent(): void
    {
        $event = $this->findEvent(5);

        self::assertTrue($event->isAllDay());
        self::assertSame(Event::FREQUENCY_YEARLY, $event->getRecurrenceFrequency());
        self::assertSame(Event::RECURRENCE_END_COUNT, $event->getRecurrenceEnd());
        self::assertSame(5, $event->getRecurrenceCount());
        self::assertNull($event->getRecurrenceUntil());
        self::assertCount(0, $event->getExceptions());
    }

    #[Test]
    public function nonRecurringEventHasNoRecurrence(): void
    {
        $event = $this->findEvent(3);

        self::assertFalse($event->isRecurring());
        self::assertSame(Event::FREQUENCY_NONE, $event->getRecurrenceFrequency());
        self::assertSame([], $event->getRecurrenceWeekdayList());
    }

    #[Test]
    public function occurrencesAreMappedFromBothSides(): void
    {
        $event = $this->findEvent(4);
        $starts = array_map(
            static fn(Occurrence $occurrence): int => (int)$occurrence->getStart()?->getTimestamp(),
            $event->getOccurrences()->toArray()
        );
        self::assertSame([1780336800, 1780509600], $starts);

        $occurrence = $this->get(OccurrenceRepository::class)->findByUid(1);
        self::assertInstanceOf(Occurrence::class, $occurrence);
        self::assertSame(1778974200, $occurrence->getStart()?->getTimestamp());
        self::assertSame(1778979600, $occurrence->getEnd()?->getTimestamp());
        self::assertFalse($occurrence->isAllDay());
        self::assertSame(3, $occurrence->getEvent()?->getUid());
        self::assertSame('Conference', $occurrence->getEvent()->getTitle());
    }
}
