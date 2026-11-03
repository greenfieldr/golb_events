<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Recurrence;

/**
 * A generated occurrence: the real start and end of one date of an event
 * as UTC timestamps.
 */
final readonly class GeneratedOccurrence
{
    public function __construct(
        public int $start,
        public int $end,
        public bool $allDay,
    ) {}
}
