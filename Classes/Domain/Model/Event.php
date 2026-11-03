<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Domain\Model;

use Greenfieldr\Golb\Domain\Model\Page;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * An event: a page of the event doktype. Inherits Golb's page metadata
 * (publish date, tags, categories, related pages, author) and adds the
 * event data.
 *
 * Start and end hold the wall clock time the editor entered. The real point
 * in time is that wall clock time interpreted in the event's time zone, see
 * getTimezone(); the generated occurrences store it as UTC timestamps.
 */
class Event extends Page
{
    public const FREQUENCY_NONE = 'none';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_YEARLY = 'yearly';

    public const MONTHLY_MODE_DAY_OF_MONTH = 'dayOfMonth';
    public const MONTHLY_MODE_WEEKDAY = 'weekday';

    public const RECURRENCE_END_NEVER = 'never';
    public const RECURRENCE_END_UNTIL = 'until';
    public const RECURRENCE_END_COUNT = 'count';

    public const WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    protected ?\DateTimeImmutable $start = null;

    protected ?\DateTimeImmutable $end = null;

    protected bool $allDay = false;

    protected bool $cancelled = false;

    protected ?Location $location = null;

    protected string $locationText = '';

    protected ?Organizer $organizer = null;

    protected string $organizerText = '';

    protected string $link = '';

    protected string $recurrenceFrequency = self::FREQUENCY_NONE;

    protected int $recurrenceInterval = 1;

    /**
     * Comma separated list of weekday codes (MO, TU, ...), see WEEKDAYS
     */
    protected string $recurrenceWeekdays = '';

    protected string $recurrenceMonthlyMode = self::MONTHLY_MODE_DAY_OF_MONTH;

    protected string $recurrenceEnd = self::RECURRENCE_END_NEVER;

    protected ?\DateTimeImmutable $recurrenceUntil = null;

    protected int $recurrenceCount = 0;

    /**
     * @var ObjectStorage<RecurrenceException>
     */
    protected ObjectStorage $exceptions;

    /**
     * @var ObjectStorage<Occurrence>
     */
    protected ObjectStorage $occurrences;

    public function initializeObject(): void
    {
        parent::initializeObject();
        $this->exceptions = new ObjectStorage();
        $this->occurrences = new ObjectStorage();
    }

    public function getStart(): ?\DateTimeImmutable
    {
        return $this->start;
    }

    public function setStart(?\DateTimeImmutable $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): ?\DateTimeImmutable
    {
        return $this->end;
    }

    public function setEnd(?\DateTimeImmutable $end): void
    {
        $this->end = $end;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): void
    {
        $this->allDay = $allDay;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): void
    {
        $this->cancelled = $cancelled;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): void
    {
        $this->location = $location;
    }

    public function getLocationText(): string
    {
        return $this->locationText;
    }

    public function setLocationText(string $locationText): void
    {
        $this->locationText = $locationText;
    }

    /**
     * Name of the location record or the free text location.
     */
    public function getLocationName(): string
    {
        return $this->location instanceof Location ? $this->location->getName() : $this->locationText;
    }

    public function getOrganizer(): ?Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?Organizer $organizer): void
    {
        $this->organizer = $organizer;
    }

    public function getOrganizerText(): string
    {
        return $this->organizerText;
    }

    public function setOrganizerText(string $organizerText): void
    {
        $this->organizerText = $organizerText;
    }

    /**
     * Name of the organizer record or the free text organizer.
     */
    public function getOrganizerName(): string
    {
        return $this->organizer instanceof Organizer ? $this->organizer->getName() : $this->organizerText;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    /**
     * Time zone the wall clock times of this event refer to: the zone of the
     * location, or the server time zone if there is none.
     */
    public function getTimezone(): \DateTimeZone
    {
        $timezone = $this->location?->getTimezone() ?? '';

        return new \DateTimeZone($timezone !== '' ? $timezone : date_default_timezone_get());
    }

    public function getRecurrenceFrequency(): string
    {
        return $this->recurrenceFrequency;
    }

    public function setRecurrenceFrequency(string $recurrenceFrequency): void
    {
        $this->recurrenceFrequency = $recurrenceFrequency;
    }

    public function isRecurring(): bool
    {
        return in_array(
            $this->recurrenceFrequency,
            [self::FREQUENCY_DAILY, self::FREQUENCY_WEEKLY, self::FREQUENCY_MONTHLY, self::FREQUENCY_YEARLY],
            true
        );
    }

    public function getRecurrenceInterval(): int
    {
        return max(1, $this->recurrenceInterval);
    }

    public function setRecurrenceInterval(int $recurrenceInterval): void
    {
        $this->recurrenceInterval = $recurrenceInterval;
    }

    public function getRecurrenceWeekdays(): string
    {
        return $this->recurrenceWeekdays;
    }

    public function setRecurrenceWeekdays(string $recurrenceWeekdays): void
    {
        $this->recurrenceWeekdays = $recurrenceWeekdays;
    }

    /**
     * Weekday codes of a weekly recurrence in calendar order, unknown codes
     * dropped.
     *
     * @return string[]
     */
    public function getRecurrenceWeekdayList(): array
    {
        $selected = array_map(trim(...), explode(',', strtoupper($this->recurrenceWeekdays)));

        return array_values(array_intersect(self::WEEKDAYS, $selected));
    }

    public function getRecurrenceMonthlyMode(): string
    {
        return $this->recurrenceMonthlyMode;
    }

    public function setRecurrenceMonthlyMode(string $recurrenceMonthlyMode): void
    {
        $this->recurrenceMonthlyMode = $recurrenceMonthlyMode;
    }

    public function getRecurrenceEnd(): string
    {
        return $this->recurrenceEnd;
    }

    public function setRecurrenceEnd(string $recurrenceEnd): void
    {
        $this->recurrenceEnd = $recurrenceEnd;
    }

    public function getRecurrenceUntil(): ?\DateTimeImmutable
    {
        return $this->recurrenceUntil;
    }

    public function setRecurrenceUntil(?\DateTimeImmutable $recurrenceUntil): void
    {
        $this->recurrenceUntil = $recurrenceUntil;
    }

    public function getRecurrenceCount(): int
    {
        return $this->recurrenceCount;
    }

    public function setRecurrenceCount(int $recurrenceCount): void
    {
        $this->recurrenceCount = $recurrenceCount;
    }

    /**
     * @return ObjectStorage<RecurrenceException>
     */
    public function getExceptions(): ObjectStorage
    {
        return $this->exceptions;
    }

    /**
     * @param ObjectStorage<RecurrenceException> $exceptions
     */
    public function setExceptions(ObjectStorage $exceptions): void
    {
        $this->exceptions = $exceptions;
    }

    public function addException(RecurrenceException $exception): void
    {
        $this->exceptions->attach($exception);
    }

    public function removeException(RecurrenceException $exception): void
    {
        $this->exceptions->detach($exception);
    }

    /**
     * @return ObjectStorage<Occurrence>
     */
    public function getOccurrences(): ObjectStorage
    {
        return $this->occurrences;
    }

    /**
     * @param ObjectStorage<Occurrence> $occurrences
     */
    public function setOccurrences(ObjectStorage $occurrences): void
    {
        $this->occurrences = $occurrences;
    }

    public function addOccurrence(Occurrence $occurrence): void
    {
        $this->occurrences->attach($occurrence);
    }

    public function removeOccurrence(Occurrence $occurrence): void
    {
        $this->occurrences->detach($occurrence);
    }
}
