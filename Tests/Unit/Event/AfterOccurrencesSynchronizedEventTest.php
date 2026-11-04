<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Event;

use Greenfieldr\GolbEvents\Event\AfterOccurrencesSynchronizedEvent;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(AfterOccurrencesSynchronizedEvent::class)]
#[CoversClass(OccurrenceRecord::class)]
final class AfterOccurrencesSynchronizedEventTest extends UnitTestCase
{
    #[Test]
    public function exposesTheChangedOccurrences(): void
    {
        $added = new OccurrenceRecord(1, 2, 100, 200, false);
        $updated = new OccurrenceRecord(3, 2, 300, 400, true);
        $removed = new OccurrenceRecord(5, 2, 500, 600, false);

        $subject = new AfterOccurrencesSynchronizedEvent(42, [$added], [$updated], [$removed], 7);

        self::assertSame(42, $subject->getEventUid());
        self::assertSame([$added], $subject->getAdded());
        self::assertSame([$updated], $subject->getUpdated());
        self::assertSame([$removed], $subject->getRemoved());
        self::assertSame(7, $subject->getCount());
        self::assertTrue($subject->hasChanges());
    }

    #[Test]
    public function hasNoChangesWhenNothingWasAddedUpdatedOrRemoved(): void
    {
        self::assertFalse((new AfterOccurrencesSynchronizedEvent(42, [], [], [], 3))->hasChanges());
    }

    #[Test]
    public function recordIsBuiltFromADatabaseRow(): void
    {
        $record = OccurrenceRecord::fromRow(['uid' => '7', 'pid' => '2', 'start' => '100', 'end' => '200', 'all_day' => '1']);

        self::assertSame(7, $record->uid);
        self::assertSame(2, $record->pid);
        self::assertSame(100, $record->start);
        self::assertSame(200, $record->end);
        self::assertTrue($record->allDay);
        self::assertFalse(OccurrenceRecord::fromRow(['uid' => 1])->allDay);
        self::assertSame(0, OccurrenceRecord::fromRow(['uid' => 'x'])->uid);
    }
}
