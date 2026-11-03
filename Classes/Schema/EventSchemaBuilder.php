<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Schema;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;

/**
 * Builds the schema.org "Event" structured data of an event page, meant to
 * be embedded as JSON-LD.
 */
final class EventSchemaBuilder
{
    public const EVENT_SCHEDULED = 'https://schema.org/EventScheduled';
    public const EVENT_CANCELLED = 'https://schema.org/EventCancelled';

    /**
     * @param Occurrence|null $occurrence the date to describe, typically the next one; the event's own start without
     * @param string $url the absolute URL of the event page
     * @return array<string, mixed> empty if the event has no start
     */
    public function build(Event $event, ?Occurrence $occurrence = null, string $url = ''): array
    {
        $schedule = EventSchedule::fromEvent($event);
        if (!$schedule instanceof EventSchedule) {
            return [];
        }
        [$start, $end] = $this->dates($schedule, $occurrence);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->getTitle(),
            'startDate' => $this->format($start, $schedule->allDay),
        ];
        if ($end > $start) {
            $schema['endDate'] = $this->format($end, $schedule->allDay);
        }
        $schema['eventStatus'] = $event->isCancelled() ? self::EVENT_CANCELLED : self::EVENT_SCHEDULED;
        if ($url !== '') {
            $schema['url'] = $url;
        }
        $description = $event->getAbstract() !== '' ? $event->getAbstract() : $event->getDescription();
        if ($description !== '') {
            $schema['description'] = $description;
        }
        $location = $this->location($event);
        if ($location !== []) {
            $schema['location'] = $location;
        }
        $organizer = $this->organizer($event);
        if ($organizer !== []) {
            $schema['organizer'] = $organizer;
        }
        if ($event->getLink() !== '') {
            $schema['offers'] = ['@type' => 'Offer', 'url' => $event->getLink()];
        }

        return $schema;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} start and end in the event's time zone
     */
    private function dates(EventSchedule $schedule, ?Occurrence $occurrence): array
    {
        $start = $occurrence?->getStart();
        if ($start instanceof \DateTimeImmutable) {
            $end = $occurrence?->getEnd() ?? $start;

            return [$start->setTimezone($schedule->timezone), $end->setTimezone($schedule->timezone)];
        }

        return [$schedule->getStartInTimezone(), $schedule->getEndInTimezone()];
    }

    private function format(\DateTimeImmutable $date, bool $allDay): string
    {
        return $date->format($allDay ? 'Y-m-d' : 'Y-m-d\TH:i:sP');
    }

    /**
     * @return array<string, mixed>
     */
    private function location(Event $event): array
    {
        $location = $event->getLocation();
        if (!$location instanceof Location) {
            return $event->getLocationText() !== '' ? ['@type' => 'Place', 'name' => $event->getLocationText()] : [];
        }

        $place = ['@type' => 'Place', 'name' => $location->getName()];
        $address = array_filter([
            'streetAddress' => $location->getStreet(),
            'postalCode' => $location->getZip(),
            'addressLocality' => $location->getCity(),
            'addressCountry' => $location->getCountry(),
        ], static fn(string $value): bool => $value !== '');
        if ($address !== []) {
            $place['address'] = ['@type' => 'PostalAddress'] + $address;
        }
        if ($location->hasCoordinates()) {
            $place['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $location->getLatitude(),
                'longitude' => $location->getLongitude(),
            ];
        }
        if ($location->getLink() !== '') {
            $place['url'] = $location->getLink();
        }

        return $place;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizer(Event $event): array
    {
        $organizer = $event->getOrganizer();
        if (!$organizer instanceof Organizer) {
            return $event->getOrganizerText() !== '' ? ['@type' => 'Organization', 'name' => $event->getOrganizerText()] : [];
        }

        return array_filter([
            '@type' => 'Organization',
            'name' => $organizer->getName(),
            'email' => $organizer->getEmail(),
            'telephone' => $organizer->getPhone(),
            'url' => $organizer->getLink(),
        ], static fn(string $value): bool => $value !== '');
    }
}
