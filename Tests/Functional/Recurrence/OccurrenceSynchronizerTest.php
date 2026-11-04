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

use Greenfieldr\GolbEvents\Event\AfterOccurrencesSynchronizedEvent;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\GeneratedOccurrence;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceGenerator;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceRecord;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use Greenfieldr\GolbEvents\Tests\Functional\AbstractOccurrenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;

#[CoversClass(OccurrenceSynchronizer::class)]
#[CoversClass(OccurrenceRecord::class)]
#[CoversClass(AfterOccurrencesSynchronizedEvent::class)]
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
    public function synchronizingAgainKeepsTheOccurrenceRecords(): void
    {
        $this->subject->synchronizeEvent(4);
        $before = $this->occurrenceRowsOf(4);
        self::assertCount(32, $before);
        self::assertNotContains(1780329600, array_column($before, 'start'), 'the stale fixture row with a wrong start was replaced');
        $total = $this->countAllOccurrences();

        $this->freezeTime('2026-01-02 12:00:00');
        $this->subject->synchronizeEvent(4);

        self::assertSame($before, $this->occurrenceRowsOf(4), 'uids and timestamps are untouched');
        self::assertSame($total, $this->countAllOccurrences());
    }

    #[Test]
    public function changedEndIsUpdatedInPlace(): void
    {
        $this->subject->synchronizeEvent(3);
        [$before] = $this->occurrenceRowsOf(3);

        $this->freezeTime('2026-01-02 12:00:00');
        $this->updatePage(3, ['tx_golbevents_end' => '2026-05-04 22:00:00']);
        $this->subject->synchronizeEvent(3);

        [$after] = $this->occurrenceRowsOf(3);
        self::assertSame($before['uid'], $after['uid']);
        self::assertSame($before['start'], $after['start']);
        self::assertSame($before['end'] + 3600, $after['end']);
        self::assertGreaterThan($before['tstamp'], $after['tstamp']);
    }

    #[Test]
    public function changedAllDayFlagIsUpdatedInPlace(): void
    {
        $this->subject->synchronizeEvent(3);
        [$before] = $this->occurrenceRowsOf(3);

        // All day: the date becomes midnight to midnight, so only the start is kept artificially
        $this->updatePage(3, ['tx_golbevents_start' => '2026-05-04 00:00:00', 'tx_golbevents_end' => null]);
        $this->subject->synchronizeEvent(3);
        [$midnight] = $this->occurrenceRowsOf(3);
        self::assertNotSame($before['uid'], $midnight['uid'], 'a new start is a new occurrence');

        $this->updatePage(3, ['tx_golbevents_all_day' => 1], ['tx_golbevents_all_day' => Connection::PARAM_INT]);
        $this->subject->synchronizeEvent(3);

        [$after] = $this->occurrenceRowsOf(3);
        self::assertSame($midnight['uid'], $after['uid']);
        self::assertSame(1, $after['all_day']);
    }

    #[Test]
    public function changedStartReplacesTheOccurrence(): void
    {
        $this->subject->synchronizeEvent(3);
        [$before] = $this->occurrenceRowsOf(3);

        $this->updatePage(3, ['tx_golbevents_start' => '2026-05-05 19:30:00', 'tx_golbevents_end' => '2026-05-05 21:00:00']);
        $this->subject->synchronizeEvent(3);

        $rows = $this->occurrenceRowsOf(3);
        self::assertCount(1, $rows);
        self::assertNotSame($before['uid'], $rows[0]['uid']);
        self::assertSame($before['start'] + 86400, $rows[0]['start']);
    }

    #[Test]
    public function changedRecurrenceKeepsTheDatesThatStillExist(): void
    {
        $this->subject->synchronizeEvent(4);
        $before = $this->occurrenceRowsOf(4);

        // Every week instead of every second week: the existing dates stay, the weeks in between are added
        $this->updatePage(4, ['tx_golbevents_recurrence_interval' => 1], ['tx_golbevents_recurrence_interval' => Connection::PARAM_INT]);
        $count = $this->subject->synchronizeEvent(4);

        $after = $this->occurrenceRowsOf(4);
        self::assertCount($count, $after);
        self::assertGreaterThan(count($before), count($after));
        foreach ($before as $row) {
            self::assertContains($row, $after, 'existing occurrence kept with its uid');
        }
    }

    #[Test]
    public function movingTheHorizonAddsOccurrencesAndKeepsTheExistingOnes(): void
    {
        $this->subject->synchronizeEvent(5);
        $before = $this->occurrenceRowsOf(5);
        self::assertCount(2, $before);

        $this->freezeTime('2027-01-01 12:00:00');
        $count = $this->subject->synchronizeEvent(5);

        $after = $this->occurrenceRowsOf(5);
        self::assertSame(3, $count);
        self::assertSame($before, array_slice($after, 0, 2));
        self::assertSame(self::serverIso('2028-07-14 00:00:00'), (new \DateTimeImmutable('@' . $after[2]['start']))->format('c'));
    }

    #[Test]
    public function movedEventPageUpdatesThePidOfItsOccurrences(): void
    {
        $this->subject->synchronizeEvent(3);
        [$before] = $this->occurrenceRowsOf(3);
        self::assertSame(2, $before['pid']);

        $this->updatePage(3, ['pid' => 1], ['pid' => Connection::PARAM_INT]);
        $this->subject->synchronizeEvent(3);

        [$after] = $this->occurrenceRowsOf(3);
        self::assertSame($before['uid'], $after['uid']);
        self::assertSame(1, $after['pid']);
    }

    #[Test]
    public function softDeletedAndDuplicateOccurrenceRowsAreCleanedUp(): void
    {
        $this->subject->synchronizeEvent(3);
        [$row] = $this->occurrenceRowsOf(3);
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_occurrence');
        $connection->insert('tx_golbevents_domain_model_occurrence', ['pid' => 2, 'event' => 3, 'start' => $row['start'], 'end' => $row['end'], 'all_day' => 0]);
        $connection->insert('tx_golbevents_domain_model_occurrence', ['pid' => 2, 'event' => 3, 'start' => 1, 'end' => 1, 'all_day' => 0, 'deleted' => 1]);
        self::assertSame(3, $this->countRowsOfEventIncludingDeleted(3));

        $this->subject->synchronizeEvent(3);

        self::assertSame([$row], $this->occurrenceRowsOf(3));
        self::assertSame(1, $this->countRowsOfEventIncludingDeleted(3));
    }

    /**
     * Registers a listener that collects the dispatched events in $dispatched.
     *
     * @param list<AfterOccurrencesSynchronizedEvent> $dispatched
     */
    private function listenForEvents(array &$dispatched): void
    {
        $container = $this->get('service_container');
        self::assertInstanceOf(Container::class, $container);
        $container->set(
            'golb-events-test-listener',
            static function (AfterOccurrencesSynchronizedEvent $event) use (&$dispatched): void {
                $dispatched[] = $event;
            }
        );
        $this->get(ListenerProvider::class)->addListener(AfterOccurrencesSynchronizedEvent::class, 'golb-events-test-listener');
    }

    /**
     * @param list<OccurrenceRecord> $records
     * @return int[]
     */
    private function startsOf(array $records): array
    {
        return array_map(static fn(OccurrenceRecord $record): int => $record->start, $records);
    }

    #[Test]
    public function eventIsDispatchedWithTheAddedOccurrences(): void
    {
        $dispatched = [];
        $this->listenForEvents($dispatched);

        // Event 5 has no occurrence in the fixture, both dates are new
        $this->subject->synchronizeEvent(5);

        self::assertCount(1, $dispatched);
        $event = $dispatched[0];
        self::assertSame(5, $event->getEventUid());
        self::assertSame(2, $event->getCount());
        self::assertTrue($event->hasChanges());
        self::assertSame([], $event->getUpdated());
        self::assertSame([], $event->getRemoved());
        self::assertCount(2, $event->getAdded());
        self::assertSame($this->occurrenceUidsOf(5), array_map(static fn(OccurrenceRecord $record): int => $record->uid, $event->getAdded()));
        self::assertSame(
            [(new \DateTimeImmutable('2026-07-14 00:00:00'))->getTimestamp(), (new \DateTimeImmutable('2027-07-14 00:00:00'))->getTimestamp()],
            $this->startsOf($event->getAdded())
        );
        self::assertSame(2, $event->getAdded()[0]->pid);
        self::assertSame((new \DateTimeImmutable('2026-07-14 23:59:59'))->getTimestamp(), $event->getAdded()[0]->end);
        self::assertTrue($event->getAdded()[0]->allDay);
    }

    #[Test]
    public function eventWithoutChangesIsDispatchedWithoutChanges(): void
    {
        $this->subject->synchronizeEvent(3);
        $dispatched = [];
        $this->listenForEvents($dispatched);

        $this->subject->synchronizeEvent(3);

        self::assertCount(1, $dispatched);
        self::assertFalse($dispatched[0]->hasChanges());
        self::assertSame(1, $dispatched[0]->getCount());
    }

    #[Test]
    public function eventReportsUpdatedAndRemovedOccurrences(): void
    {
        $this->subject->synchronizeEvent(4);
        $before = $this->occurrenceRowsOf(4);
        $dispatched = [];
        $this->listenForEvents($dispatched);

        // Later end and an end date in October: the remaining dates are updated, the later ones removed
        $this->updatePage(4, ['tx_golbevents_end' => '2026-06-01 20:00:00', 'tx_golbevents_recurrence_until' => '2026-10-31']);
        $this->subject->synchronizeEvent(4);

        self::assertCount(1, $dispatched);
        $event = $dispatched[0];
        self::assertSame([], $event->getAdded());
        self::assertNotSame([], $event->getUpdated());
        self::assertNotSame([], $event->getRemoved());
        self::assertCount(count($before), [...$event->getUpdated(), ...$event->getRemoved()]);
        self::assertCount($event->getCount(), $event->getUpdated());
        $updated = $event->getUpdated()[0];
        self::assertSame($before[0]['uid'], $updated->uid);
        self::assertSame($before[0]['start'], $updated->start);
        self::assertSame($before[0]['start'] + 7200, $updated->end, 'the new end');
        $removed = $event->getRemoved();
        self::assertSame(array_column(array_slice($before, -count($removed)), 'start'), $this->startsOf($removed));
        self::assertSame(end($before)['uid'], end($removed)->uid);
    }

    #[Test]
    public function eventIsDispatchedWhenThePageIsNoEventAnyMore(): void
    {
        $this->subject->synchronizeEvent(3);
        [$row] = $this->occurrenceRowsOf(3);
        $dispatched = [];
        $this->listenForEvents($dispatched);

        $this->updatePage(3, ['doktype' => 1], ['doktype' => Connection::PARAM_INT]);
        $this->subject->synchronizeEvent(3);

        self::assertCount(1, $dispatched);
        self::assertSame(0, $dispatched[0]->getCount());
        self::assertSame([$row['uid']], array_map(static fn(OccurrenceRecord $record): int => $record->uid, $dispatched[0]->getRemoved()));
    }

    #[Test]
    public function deletingTheOccurrencesDispatchesTheRemovedOnes(): void
    {
        $this->subject->synchronizeEvent(5);
        $uids = $this->occurrenceUidsOf(5);
        $dispatched = [];
        $this->listenForEvents($dispatched);

        $this->subject->deleteOccurrencesOfEvent(5);

        self::assertSame([], $this->occurrencesOf(5));
        self::assertCount(1, $dispatched);
        self::assertSame(5, $dispatched[0]->getEventUid());
        self::assertSame($uids, array_map(static fn(OccurrenceRecord $record): int => $record->uid, $dispatched[0]->getRemoved()));
    }

    #[Test]
    public function synchronizeAllDispatchesForOrphansToo(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_occurrence')->insert(
            'tx_golbevents_domain_model_occurrence',
            ['pid' => 2, 'event' => 6, 'start' => 1, 'end' => 1, 'all_day' => 0]
        );
        $dispatched = [];
        $this->listenForEvents($dispatched);

        $this->subject->synchronizeAll();

        $byEvent = [];
        foreach ($dispatched as $event) {
            $byEvent[$event->getEventUid()] = $event;
        }
        self::assertSame([3, 4, 5, 6], array_keys($byEvent));
        self::assertSame(0, $byEvent[6]->getCount());
        self::assertSame([1], $this->startsOf($byEvent[6]->getRemoved()));
    }

    /**
     * The test helpers of the base class use the default restrictions and
     * leave soft deleted rows out; the synchronizer sees them.
     */
    private function countRowsOfEventIncludingDeleted(int $eventUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_golbevents_domain_model_occurrence');
        $queryBuilder->getRestrictions()->removeAll();

        return self::toInt(
            $queryBuilder->count('uid')
                ->from('tx_golbevents_domain_model_occurrence')
                ->where($queryBuilder->expr()->eq('event', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchOne()
        );
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
