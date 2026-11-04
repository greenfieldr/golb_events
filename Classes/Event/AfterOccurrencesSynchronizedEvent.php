<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Event;

use Greenfieldr\GolbEvents\Recurrence\OccurrenceRecord;

/**
 * Dispatched after the occurrences of an event page were synchronized with
 * its dates: when the page (or one of its excluded dates, or the time zone
 * of its location) was saved, copied, moved, deleted or undeleted, and by
 * the command "golbevents:occurrences:generate".
 *
 * Listeners learn which occurrence records were added, updated in place
 * (same start, changed end, all day flag or pid) and removed, so they can
 * keep their own references to occurrences in sync, e.g. notify the
 * participants of a removed date.
 */
final readonly class AfterOccurrencesSynchronizedEvent
{
    /**
     * @param int $eventUid uid of the event page; it may no longer be an event or exist
     * @param list<OccurrenceRecord> $added the inserted occurrences
     * @param list<OccurrenceRecord> $updated the occurrences updated in place, with their new values
     * @param list<OccurrenceRecord> $removed the deleted occurrences, with their last values
     * @param int $count number of occurrences the event has now
     */
    public function __construct(
        private int $eventUid,
        private array $added,
        private array $updated,
        private array $removed,
        private int $count,
    ) {}

    public function getEventUid(): int
    {
        return $this->eventUid;
    }

    /**
     * @return list<OccurrenceRecord>
     */
    public function getAdded(): array
    {
        return $this->added;
    }

    /**
     * @return list<OccurrenceRecord>
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * @return list<OccurrenceRecord>
     */
    public function getRemoved(): array
    {
        return $this->removed;
    }

    /**
     * Number of occurrences stored for the event after the synchronization,
     * 0 if the page is no event (any more).
     */
    public function getCount(): int
    {
        return $this->count;
    }

    public function hasChanges(): bool
    {
        return $this->added !== [] || $this->updated !== [] || $this->removed !== [];
    }
}
