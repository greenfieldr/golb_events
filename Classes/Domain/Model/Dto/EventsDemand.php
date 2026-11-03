<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Domain\Model\Dto;

use Greenfieldr\Golb\Domain\Model\Dto\PostsDemand;

/**
 * Describes which event occurrences should be fetched. Extends Golb's demand
 * (categories, tags, excluded pages, limit, offset, sorting, pagination, top
 * posts) by the event specific mode and date range.
 */
class EventsDemand extends PostsDemand
{
    /**
     * Occurrences that have not ended yet
     */
    public const MODE_UPCOMING = 'upcoming';

    /**
     * Occurrences that have ended
     */
    public const MODE_PAST = 'past';

    /**
     * All occurrences
     */
    public const MODE_ALL = 'all';

    /**
     * Occurrences overlapping the range between "from" and "to"
     */
    public const MODE_RANGE = 'range';

    public const MODE_DEFAULT = self::MODE_UPCOMING;

    public const ORDER_START = 'start';

    protected ?string $mode = null;

    protected ?\DateTimeImmutable $from = null;

    protected ?\DateTimeImmutable $to = null;

    public function getMode(): string
    {
        return $this->mode ?? self::MODE_DEFAULT;
    }

    public function setMode(string $mode): static
    {
        $this->mode = in_array($mode, [self::MODE_UPCOMING, self::MODE_PAST, self::MODE_ALL, self::MODE_RANGE], true)
            ? $mode
            : null;

        return $this;
    }

    public function hasMode(): bool
    {
        return $this->mode !== null;
    }

    public function getFrom(): ?\DateTimeImmutable
    {
        return $this->from;
    }

    public function setFrom(?\DateTimeImmutable $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function hasFrom(): bool
    {
        return $this->from instanceof \DateTimeImmutable;
    }

    public function getTo(): ?\DateTimeImmutable
    {
        return $this->to;
    }

    public function setTo(?\DateTimeImmutable $to): static
    {
        $this->to = $to;

        return $this;
    }

    public function hasTo(): bool
    {
        return $this->to instanceof \DateTimeImmutable;
    }

    /**
     * Events are sorted by the start of their occurrences by default.
     */
    public function getOrder(): string
    {
        return $this->order ?? self::ORDER_START;
    }

    /**
     * Upcoming occurrences are listed in chronological order, past ones with
     * the most recent first.
     */
    public function getOrderDirection(): string
    {
        return $this->orderDirection ?? ($this->getMode() === self::MODE_PAST ? 'DESC' : 'ASC');
    }
}
