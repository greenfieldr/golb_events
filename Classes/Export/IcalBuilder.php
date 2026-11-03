<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Export;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;

/**
 * Renders an event as iCalendar (RFC 5545) document with one VEVENT. Times
 * are written as wall clock times with the IANA time zone of the event
 * (TZID), recurring events carry their RRULE and EXDATEs.
 */
final readonly class IcalBuilder
{
    public const PRODUCT_ID = '-//Golb Events//TYPO3//EN';

    public function __construct(private RecurrenceRuleBuilder $ruleBuilder) {}

    /**
     * @param string $url the absolute URL of the event page, added as URL property if given
     * @param string $host the host part of the UID, e.g. the site's host
     * @param \DateTimeImmutable|null $now the DTSTAMP, defaults to the current time
     * @return string|null null if the event has no start
     */
    public function build(Event $event, string $url = '', string $host = 'golb-events', ?\DateTimeImmutable $now = null): ?string
    {
        $schedule = EventSchedule::fromEvent($event);
        if (!$schedule instanceof EventSchedule) {
            return null;
        }
        $timezone = $schedule->timezone;
        $start = $schedule->getStartInTimezone();
        $end = $schedule->getEndInTimezone();
        $utc = new \DateTimeZone('UTC');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODUCT_ID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:golb-event-' . (int)$event->getUid() . '@' . ($host !== '' ? $host : 'golb-events'),
            'DTSTAMP:' . ($now ?? new \DateTimeImmutable())->setTimezone($utc)->format('Ymd\THis\Z'),
        ];

        if ($schedule->allDay) {
            // DTEND of all day events is exclusive
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $end->modify('+1 second')->format('Ymd');
        } else {
            $lines[] = $this->dateTimeProperty('DTSTART', $start, $timezone);
            $lines[] = $this->dateTimeProperty('DTEND', $end, $timezone);
        }

        $rule = $this->ruleBuilder->buildString($schedule);
        if ($rule !== null) {
            $lines[] = 'RRULE:' . $rule;
            if ($schedule->exceptionDates !== []) {
                $lines[] = $this->exceptionDatesProperty($schedule, $start, $timezone);
            }
        }

        $lines[] = 'SUMMARY:' . $this->escape($event->getTitle());
        $description = $event->getAbstract() !== '' ? $event->getAbstract() : $event->getDescription();
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . $this->escape($description);
        }
        $location = $this->locationText($event);
        if ($location !== '') {
            $lines[] = 'LOCATION:' . $this->escape($location);
        }
        if ($event->getLocation() instanceof Location && $event->getLocation()->hasCoordinates()) {
            $lines[] = sprintf('GEO:%s;%s', $this->coordinate($event->getLocation()->getLatitude()), $this->coordinate($event->getLocation()->getLongitude()));
        }
        if ($url !== '') {
            $lines[] = 'URL:' . $this->escape($url);
        }
        $organizer = $event->getOrganizer();
        if ($organizer instanceof Organizer && $organizer->getEmail() !== '') {
            $lines[] = 'ORGANIZER;CN=' . $this->quoteParameter($organizer->getName()) . ':mailto:' . $organizer->getEmail();
        }
        $lines[] = 'STATUS:' . ($event->isCancelled() ? 'CANCELLED' : 'CONFIRMED');
        if ($event->getTstamp() !== null && $event->getTstamp() > 0) {
            $lines[] = 'LAST-MODIFIED:' . (new \DateTimeImmutable('@' . $event->getTstamp()))->format('Ymd\THis\Z');
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
    }

    private function dateTimeProperty(string $name, \DateTimeImmutable $date, \DateTimeZone $timezone): string
    {
        if ($timezone->getName() === 'UTC') {
            return $name . ':' . $date->format('Ymd\THis\Z');
        }

        return $name . ';TZID=' . $timezone->getName() . ':' . $date->format('Ymd\THis');
    }

    private function exceptionDatesProperty(EventSchedule $schedule, \DateTimeImmutable $start, \DateTimeZone $timezone): string
    {
        if ($schedule->allDay) {
            return 'EXDATE;VALUE=DATE:' . implode(',', array_map(static fn(string $date): string => str_replace('-', '', $date), $schedule->exceptionDates));
        }
        $values = array_map(
            static fn(string $date): string => (new \DateTimeImmutable($date . ' ' . $start->format('H:i:s'), $timezone))->format('Ymd\THis'),
            $schedule->exceptionDates
        );
        if ($timezone->getName() === 'UTC') {
            return 'EXDATE:' . implode(',', array_map(static fn(string $value): string => $value . 'Z', $values));
        }

        return 'EXDATE;TZID=' . $timezone->getName() . ':' . implode(',', $values);
    }

    private function locationText(Event $event): string
    {
        $location = $event->getLocation();
        if (!$location instanceof Location) {
            return $event->getLocationText();
        }
        $parts = [$location->getName(), $location->getStreet(), trim($location->getZip() . ' ' . $location->getCity())];

        return implode(', ', array_filter(array_map(trim(...), $parts), static fn(string $part): bool => $part !== ''));
    }

    private function coordinate(?float $value): string
    {
        return rtrim(rtrim(number_format($value ?? 0.0, 6, '.', ''), '0'), '.');
    }

    /**
     * Escapes a text value (RFC 5545, section 3.3.11).
     */
    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $value);
    }

    private function quoteParameter(string $value): string
    {
        return '"' . str_replace(['"', "\r", "\n"], ['', ' ', ' '], $value) . '"';
    }

    /**
     * Folds a content line at 75 octets (RFC 5545, section 3.1).
     */
    private function fold(string $line): string
    {
        $folded = '';
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded .= $chunk . "\r\n ";
            $line = substr($line, strlen($chunk));
            // Continuation lines start with a space that counts
            $limit = 74;
        }

        return $folded . $line;
    }
}
