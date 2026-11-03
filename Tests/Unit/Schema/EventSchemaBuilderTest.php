<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Schema;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Schema\EventSchemaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(EventSchemaBuilder::class)]
#[CoversClass(EventSchedule::class)]
final class EventSchemaBuilderTest extends UnitTestCase
{
    private EventSchemaBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new EventSchemaBuilder();
    }

    private function event(string $title, ?string $start, ?string $end = null): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setStart($start === null ? null : new \DateTimeImmutable($start));
        $event->setEnd($end === null ? null : new \DateTimeImmutable($end));

        return $event;
    }

    #[Test]
    public function eventWithoutStartHasNoSchema(): void
    {
        self::assertSame([], $this->subject->build($this->event('No date', null)));
    }

    #[Test]
    public function minimalEventUsesItsOwnDates(): void
    {
        $event = $this->event('Conference', '2026-05-04 19:30:00', '2026-05-04 21:00:00');
        $location = new Location();
        $location->setTimezone('America/New_York');
        $event->setLocation($location);

        $schema = $this->subject->build($event);

        self::assertSame('https://schema.org', $schema['@context']);
        self::assertSame('Event', $schema['@type']);
        self::assertSame('Conference', $schema['name']);
        self::assertSame('2026-05-04T19:30:00-04:00', $schema['startDate']);
        self::assertSame('2026-05-04T21:00:00-04:00', $schema['endDate']);
        self::assertSame(EventSchemaBuilder::EVENT_SCHEDULED, $schema['eventStatus']);
        self::assertArrayNotHasKey('url', $schema);
        self::assertArrayNotHasKey('description', $schema);
        self::assertArrayNotHasKey('organizer', $schema);
        self::assertArrayNotHasKey('offers', $schema);
    }

    #[Test]
    public function occurrenceDatesTakePrecedenceOverTheEventDates(): void
    {
        $event = $this->event('Meetup', '2026-06-01 18:00:00', '2026-06-01 20:00:00');
        $location = new Location();
        $location->setTimezone('Europe/Berlin');
        $event->setLocation($location);
        $occurrence = new Occurrence();
        $occurrence->setStart(new \DateTimeImmutable('2026-06-15 16:00:00', new \DateTimeZone('UTC')));
        $occurrence->setEnd(new \DateTimeImmutable('2026-06-15 18:00:00', new \DateTimeZone('UTC')));

        $schema = $this->subject->build($event, $occurrence, 'https://example.org/events/meetup');

        self::assertSame('2026-06-15T18:00:00+02:00', $schema['startDate']);
        self::assertSame('2026-06-15T20:00:00+02:00', $schema['endDate']);
        self::assertSame('https://example.org/events/meetup', $schema['url']);
    }

    #[Test]
    public function allDayEventsUseDatesOnly(): void
    {
        $event = $this->event('Festival', '2026-07-01 10:00:00', '2026-07-03 08:00:00');
        $event->setAllDay(true);

        $schema = $this->subject->build($event);

        self::assertSame('2026-07-01', $schema['startDate']);
        self::assertSame('2026-07-03', $schema['endDate']);
    }

    #[Test]
    public function eventWithoutEndHasNoEndDate(): void
    {
        self::assertArrayNotHasKey('endDate', $this->subject->build($this->event('Talk', '2026-05-04 19:30:00')));
    }

    #[Test]
    public function locationOrganizerStatusAndOfferAreMapped(): void
    {
        $event = $this->event('Concert', '2026-05-04 19:30:00', '2026-05-04 21:00:00');
        $event->setAbstract('A concert.');
        $event->setCancelled(true);
        $event->setLink('https://tickets.example.org');
        $location = new Location();
        $location->setName('Carnegie Hall');
        $location->setStreet('881 7th Ave');
        $location->setZip('10019');
        $location->setCity('New York');
        $location->setCountry('US');
        $location->setLatitude(40.765);
        $location->setLongitude(-73.98);
        $location->setLink('https://hall.example.org');
        $event->setLocation($location);
        $organizer = new Organizer();
        $organizer->setName('Golb Society');
        $organizer->setEmail('mail@example.org');
        $organizer->setLink('https://example.org');
        $event->setOrganizer($organizer);

        $schema = $this->subject->build($event);

        self::assertSame(EventSchemaBuilder::EVENT_CANCELLED, $schema['eventStatus']);
        self::assertSame('A concert.', $schema['description']);
        self::assertSame([
            '@type' => 'Place',
            'name' => 'Carnegie Hall',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '881 7th Ave',
                'postalCode' => '10019',
                'addressLocality' => 'New York',
                'addressCountry' => 'US',
            ],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 40.765, 'longitude' => -73.98],
            'url' => 'https://hall.example.org',
        ], $schema['location']);
        self::assertSame([
            '@type' => 'Organization',
            'name' => 'Golb Society',
            'email' => 'mail@example.org',
            'url' => 'https://example.org',
        ], $schema['organizer']);
        self::assertSame(['@type' => 'Offer', 'url' => 'https://tickets.example.org'], $schema['offers']);
    }

    #[Test]
    public function freeTextLocationAndOrganizerBecomeNamedPlaceAndOrganization(): void
    {
        $event = $this->event('Walk', '2026-05-04 09:00:00');
        $event->setLocationText('Town hall');
        $event->setOrganizerText('Volunteers');
        $event->setDescription('Meta description');

        $schema = $this->subject->build($event);

        self::assertSame(['@type' => 'Place', 'name' => 'Town hall'], $schema['location']);
        self::assertSame(['@type' => 'Organization', 'name' => 'Volunteers'], $schema['organizer']);
        self::assertSame('Meta description', $schema['description'], 'the page description is the fallback');
    }
}
