<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Recurrence;

use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\GeneratedOccurrence;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceGenerator;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use Greenfieldr\GolbEvents\Tests\Functional\AbstractOccurrenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;

#[CoversClass(OccurrenceSynchronizer::class)]
#[CoversClass(OccurrenceGenerator::class)]
#[CoversClass(GeneratedOccurrence::class)]
#[CoversClass(EventSchedule::class)]
#[CoversClass(RecurrenceRuleBuilder::class)]
final class OccurrenceSynchronizerTest extends AbstractOccurrenceTestCase
{
    private OccurrenceSynchronizer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventSite.csv');
        $this->freezeTime();
        $this->subject = $this->get(OccurrenceSynchronizer::class);
    }

    #[Test]
    public function horizonIsTwoYearsFromNowByDefault(): void
    {
        self::assertSame('2028-01-01', $this->subject->getHorizon()->format('Y-m-d'));
    }

    #[Test]
    public function horizonUsesTheExtensionSetting(): void
    {
        $extensionConfiguration = $this->get(ExtensionConfiguration::class);
        $extensionConfiguration->set('golb_events', ['horizonYears' => '5']);
        try {
            self::assertSame('2031-01-01', $this->subject->getHorizon()->format('Y-m-d'));
        } finally {
            // The setting is written to the configuration of the test instance
            $extensionConfiguration->set('golb_events', ['horizonYears' => '2']);
        }
    }

    #[Test]
    public function nonRecurringEventGetsOneOccurrenceInTheTimezoneOfItsLocation(): void
    {
        $count = $this->subject->synchronizeEvent(3);

        self::assertSame(1, $count);
        self::assertSame(['2026-05-04T19:30:00-04:00'], $this->occurrenceStartsOf(3, 'America/New_York'));
        self::assertSame([['start' => 1777937400, 'end' => 1777942800, 'all_day' => 0]], $this->occurrencesOf(3));
        self::assertSame(1, $this->occurrenceCountFieldOf(3));
    }

    #[Test]
    public function recurringEventIsExpandedWithExceptionsUpToTheUntilDate(): void
    {
        $count = $this->subject->synchronizeEvent(4);
        $starts = $this->occurrenceStartsOf(4);

        // Every second week on Monday and Wednesday from 2026-06-01 until 2026-12-31,
        // minus the excluded dates 2026-06-08 and 2026-06-10 (which fall into a
        // skipped week anyway) and nothing excluded in the counted weeks.
        self::assertCount($count, $starts);
        self::assertSame(self::serverIso('2026-06-01 18:00:00'), $starts[0]);
        self::assertSame(self::serverIso('2026-06-03 18:00:00'), $starts[1]);
        self::assertSame(self::serverIso('2026-06-15 18:00:00'), $starts[2]);
        self::assertSame(self::serverIso('2026-12-30 18:00:00'), end($starts));
        self::assertSame(32, $count);
        self::assertSame(32, $this->occurrenceCountFieldOf(4));
    }

    #[Test]
    public function countBasedAllDayRecurrenceIsCappedByTheHorizon(): void
    {
        $count = $this->subject->synchronizeEvent(5);

        self::assertSame(2, $count, 'five yearly occurrences from 2026, horizon 2028-01-01');
        self::assertSame([self::serverIso('2026-07-14 00:00:00'), self::serverIso('2027-07-14 00:00:00')], $this->occurrenceStartsOf(5));
        self::assertSame(1, $this->occurrencesOf(5)[0]['all_day']);
    }

    #[Test]
    public function synchronizingReplacesExistingOccurrences(): void
    {
        $this->subject->synchronizeEvent(4);
        $before = $this->countAllOccurrences();

        $this->subject->synchronizeEvent(4);

        self::assertSame($before, $this->countAllOccurrences());
    }

    #[Test]
    public function pagesThatAreNoEventsLoseTheirOccurrences(): void
    {
        $this->subject->synchronizeEvent(3);
        $this->updatePage(3, ['doktype' => 1], ['doktype' => Connection::PARAM_INT]);

        self::assertSame(0, $this->subject->synchronizeEvent(3));
        self::assertSame([], $this->occurrencesOf(3));
        self::assertSame(0, $this->occurrenceCountFieldOf(3));
        self::assertSame(0, $this->subject->synchronizeEvent(6), 'a blog post');
        self::assertSame(0, $this->subject->synchronizeEvent(999), 'a missing page');
    }

    #[Test]
    public function deletedAndTranslatedPagesGetNoOccurrences(): void
    {
        $this->updatePage(3, ['deleted' => 1], ['deleted' => Connection::PARAM_INT]);
        $this->updatePage(4, ['sys_language_uid' => 1], ['sys_language_uid' => Connection::PARAM_INT]);

        self::assertSame(0, $this->subject->synchronizeEvent(3));
        self::assertSame(0, $this->subject->synchronizeEvent(4));
    }

    #[Test]
    public function eventWithoutStartGetsNoOccurrences(): void
    {
        $this->updatePage(3, ['tx_golbevents_start' => null]);

        self::assertSame(0, $this->subject->synchronizeEvent(3));
    }

    #[Test]
    public function synchronizeAllRegeneratesEveryEventAndRemovesOrphans(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_occurrence')->insert(
            'tx_golbevents_domain_model_occurrence',
            ['pid' => 2, 'event' => 6, 'start' => 1, 'end' => 1, 'all_day' => 0]
        );

        $count = $this->subject->synchronizeAll();

        self::assertSame(3, $count);
        self::assertSame(1 + 32 + 2, $this->countAllOccurrences());
        self::assertSame([], $this->occurrencesOf(6), 'orphan removed');
    }

    #[Test]
    public function synchronizeLocationRegeneratesTheEventsHeldThere(): void
    {
        $this->subject->synchronizeEvent(3);
        $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_location')->update(
            'tx_golbevents_domain_model_location',
            ['timezone' => 'Europe/Berlin'],
            ['uid' => 1]
        );

        $count = $this->subject->synchronizeLocation(1);

        self::assertSame(1, $count);
        self::assertSame(['2026-05-04T19:30:00+02:00'], $this->occurrenceStartsOf(3, 'Europe/Berlin'));
        self::assertSame(0, $this->subject->synchronizeLocation(2), 'no events there');
    }
}
