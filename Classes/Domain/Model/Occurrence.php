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

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * One occurrence of an event (table tx_golbevents_domain_model_occurrence):
 * the real point in time of a single date of the event. Occurrences are
 * generated from the event page, a non recurring event has exactly one.
 */
class Occurrence extends AbstractEntity
{
    protected ?Event $event = null;

    protected ?\DateTimeImmutable $start = null;

    protected ?\DateTimeImmutable $end = null;

    protected bool $allDay = false;

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): void
    {
        $this->event = $event;
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
}
