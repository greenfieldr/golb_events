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
 * A stored occurrence row (table tx_golbevents_domain_model_occurrence):
 * its uid, pid and the real start and end as UTC timestamps.
 */
final readonly class OccurrenceRecord
{
    public function __construct(
        public int $uid,
        public int $pid,
        public int $start,
        public int $end,
        public bool $allDay,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $int = static fn(mixed $value): int => is_numeric($value) ? (int)$value : 0;

        return new self(
            $int($row['uid'] ?? 0),
            $int($row['pid'] ?? 0),
            $int($row['start'] ?? 0),
            $int($row['end'] ?? 0),
            $int($row['all_day'] ?? 0) !== 0
        );
    }
}
