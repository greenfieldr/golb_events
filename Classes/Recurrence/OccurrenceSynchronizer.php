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

use Greenfieldr\GolbEvents\Constants;
use Greenfieldr\GolbEvents\Event\AfterOccurrencesSynchronizedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Keeps the generated occurrences (table tx_golbevents_domain_model_occurrence)
 * in sync with the event pages. Works on the live, default language records
 * through the database directly, so it can run from DataHandler hooks and
 * from the command line.
 *
 * Occurrence records are stable: an existing occurrence is matched by its
 * start, kept with its uid and updated in place if its end, all day flag or
 * pid changed. Only dates that vanished are deleted and only new dates are
 * inserted, so other extensions can safely reference occurrence uids. The
 * changes are announced with the AfterOccurrencesSynchronizedEvent.
 */
class OccurrenceSynchronizer
{
    public const TABLE_PAGES = 'pages';
    public const TABLE_OCCURRENCES = 'tx_golbevents_domain_model_occurrence';
    public const TABLE_EXCEPTIONS = 'tx_golbevents_domain_model_exception';
    public const TABLE_LOCATIONS = 'tx_golbevents_domain_model_location';

    public const DEFAULT_HORIZON_YEARS = 2;

    private const OCCURRENCE_COLUMNS = ['pid', 'tstamp', 'crdate', 'event', 'start', 'end', 'all_day'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OccurrenceGenerator $generator,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Context $context,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Synchronizes the occurrences of one event page with its dates. Pages
     * that are no event (other doktype, translation, workspace version,
     * deleted) lose their occurrences. Returns the number of occurrences
     * stored.
     */
    public function synchronizeEvent(int $pageUid): int
    {
        $row = $this->findLiveEventRow($pageUid);
        $generated = [];
        $pid = 0;
        if ($row !== null) {
            $pid = $this->toInt($row['pid'] ?? 0);
            $schedule = EventSchedule::fromRecord(
                $row,
                $this->findLocationTimezone($this->toInt($row['tx_golbevents_location'] ?? 0)),
                $this->findExceptionDates($pageUid)
            );
            $generated = $schedule instanceof EventSchedule ? $this->generator->generate($schedule, $this->getHorizon()) : [];
        }

        $this->storeOccurrences($pageUid, $pid, $generated);
        $this->updateOccurrenceCount($pageUid, count($generated));

        return count($generated);
    }

    /**
     * Synchronizes the occurrences of every event page and removes
     * occurrences of records that are no events any more. Returns the number
     * of events.
     */
    public function synchronizeAll(): int
    {
        $eventUids = $this->findAllLiveEventUids();
        foreach ($eventUids as $uid) {
            $this->synchronizeEvent($uid);
        }

        foreach ($this->findOrphanedEventUids($eventUids) as $orphanUid) {
            $this->deleteOccurrencesOfEvent($orphanUid);
        }

        return count($eventUids);
    }

    /**
     * Synchronizes the occurrences of all events held at a location, e.g.
     * after its time zone changed. Returns the number of events.
     */
    public function synchronizeLocation(int $locationUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PAGES);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid')
            ->from(self::TABLE_PAGES)
            ->where(
                $queryBuilder->expr()->eq('tx_golbevents_location', $queryBuilder->createNamedParameter($locationUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(Constants::EVENT_DOKTYPE, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery();

        $count = 0;
        while (($uid = $result->fetchOne()) !== false) {
            $this->synchronizeEvent($this->toInt($uid));
            $count++;
        }

        return $count;
    }

    /**
     * Removes all occurrences of an event, e.g. when its page is deleted.
     */
    public function deleteOccurrencesOfEvent(int $pageUid): void
    {
        $this->storeOccurrences($pageUid, 0, []);
    }

    /**
     * Occurrences are generated up to this point in time: now plus the
     * configured number of years (extension setting "horizonYears").
     */
    public function getHorizon(): \DateTimeImmutable
    {
        $years = self::DEFAULT_HORIZON_YEARS;
        try {
            $configured = $this->extensionConfiguration->get('golb_events', 'horizonYears');
            if (is_numeric($configured) && (int)$configured > 0) {
                $years = (int)$configured;
            }
        } catch (\Throwable) {
            // Not configured: keep the default
        }
        $now = $this->context->getPropertyFromAspect('date', 'full');
        if (!$now instanceof \DateTimeImmutable) {
            $now = new \DateTimeImmutable();
        }

        return $now->modify('+' . $years . ' years');
    }

    /**
     * Brings the stored occurrences of an event in line with the generated
     * ones: existing records are matched by their start and kept (updated if
     * end, all day flag or pid differ), vanished dates are deleted and new
     * dates inserted. Dispatches the AfterOccurrencesSynchronizedEvent.
     *
     * @param GeneratedOccurrence[] $generated
     */
    protected function storeOccurrences(int $eventUid, int $pid, array $generated): void
    {
        $existingByStart = [];
        $remove = [];
        $removed = [];
        foreach ($this->findOccurrenceRows($eventUid) as $row) {
            // Soft deleted rows and duplicate starts cannot be matched and are cleaned up
            if ($this->toInt($row['deleted'] ?? 0) !== 0 || isset($existingByStart[$this->toInt($row['start'])])) {
                $remove[] = $this->toInt($row['uid']);
                $removed[] = OccurrenceRecord::fromRow($row);
                continue;
            }
            $existingByStart[$this->toInt($row['start'])] = $row;
        }

        $now = $this->toInt($this->context->getPropertyFromAspect('date', 'timestamp'));
        $insert = [];
        $update = [];
        $updated = [];
        foreach ($generated as $occurrence) {
            $existing = $existingByStart[$occurrence->start] ?? null;
            if ($existing === null) {
                $insert[] = [
                    'pid' => $pid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'event' => $eventUid,
                    'start' => $occurrence->start,
                    'end' => $occurrence->end,
                    'all_day' => (int)$occurrence->allDay,
                ];
                continue;
            }
            unset($existingByStart[$occurrence->start]);
            if ($this->toInt($existing['end']) !== $occurrence->end
                || $this->toInt($existing['all_day']) !== (int)$occurrence->allDay
                || $this->toInt($existing['pid']) !== $pid
            ) {
                $update[$this->toInt($existing['uid'])] = [
                    'pid' => $pid,
                    'tstamp' => $now,
                    'end' => $occurrence->end,
                    'all_day' => (int)$occurrence->allDay,
                ];
                $updated[] = new OccurrenceRecord($this->toInt($existing['uid']), $pid, $occurrence->start, $occurrence->end, $occurrence->allDay);
            }
        }
        foreach ($existingByStart as $row) {
            $remove[] = $this->toInt($row['uid']);
            $removed[] = OccurrenceRecord::fromRow($row);
        }

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_OCCURRENCES);
        $connection->transactional(function () use ($connection, $remove, $update, $insert): void {
            if ($remove !== []) {
                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder->delete(self::TABLE_OCCURRENCES)
                    ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($remove, Connection::PARAM_INT_ARRAY)))
                    ->executeStatement();
            }
            foreach ($update as $uid => $fields) {
                $connection->update(
                    self::TABLE_OCCURRENCES,
                    $fields,
                    ['uid' => $uid],
                    array_fill_keys([...array_keys($fields), 'uid'], Connection::PARAM_INT)
                );
            }
            if ($insert !== []) {
                $connection->bulkInsert(
                    self::TABLE_OCCURRENCES,
                    $insert,
                    self::OCCURRENCE_COLUMNS,
                    array_fill_keys(self::OCCURRENCE_COLUMNS, Connection::PARAM_INT)
                );
            }
        });

        $added = [];
        if ($insert !== []) {
            $insertedStarts = array_flip(array_column($insert, 'start'));
            foreach ($this->findOccurrenceRows($eventUid) as $row) {
                if (isset($insertedStarts[$this->toInt($row['start'])]) && $this->toInt($row['deleted'] ?? 0) === 0) {
                    $added[] = OccurrenceRecord::fromRow($row);
                }
            }
        }

        $this->eventDispatcher->dispatch(
            new AfterOccurrencesSynchronizedEvent($eventUid, $added, $updated, $removed, count($generated))
        );
    }

    /**
     * All stored occurrence rows of an event, including soft deleted ones.
     *
     * @return list<array<string, mixed>>
     */
    protected function findOccurrenceRows(int $eventUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_OCCURRENCES);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder->select('uid', 'pid', 'start', 'end', 'all_day', 'deleted')
            ->from(self::TABLE_OCCURRENCES)
            ->where($queryBuilder->expr()->eq('event', $queryBuilder->createNamedParameter($eventUid, Connection::PARAM_INT)))
            ->orderBy('start')
            ->addOrderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * The event uids referenced by occurrences that belong to none of the
     * given live events.
     *
     * @param int[] $liveEventUids
     * @return int[]
     */
    protected function findOrphanedEventUids(array $liveEventUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_OCCURRENCES);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('event')
            ->from(self::TABLE_OCCURRENCES)
            ->groupBy('event')
            ->orderBy('event');
        if ($liveEventUids !== []) {
            $queryBuilder->where(
                $queryBuilder->expr()->notIn('event', $queryBuilder->createNamedParameter($liveEventUids, Connection::PARAM_INT_ARRAY))
            );
        }

        return array_map($this->toInt(...), $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLiveEventRow(int $pageUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PAGES);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')
            ->from(self::TABLE_PAGES)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(Constants::EVENT_DOKTYPE, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * @return int[]
     */
    private function findAllLiveEventUids(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PAGES);
        $queryBuilder->getRestrictions()->removeAll();
        $uids = $queryBuilder->select('uid')
            ->from(self::TABLE_PAGES)
            ->where(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(Constants::EVENT_DOKTYPE, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map($this->toInt(...), $uids);
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    private function findLocationTimezone(int $locationUid): string
    {
        if ($locationUid <= 0) {
            return '';
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_LOCATIONS);
        $queryBuilder->getRestrictions()->removeAll();
        $timezone = $queryBuilder->select('timezone')
            ->from(self::TABLE_LOCATIONS)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($locationUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return is_string($timezone) ? $timezone : '';
    }

    /**
     * @return string[]
     */
    private function findExceptionDates(int $pageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_EXCEPTIONS);
        $queryBuilder->getRestrictions()->removeAll();
        $dates = $queryBuilder->select('date')
            ->from(self::TABLE_EXCEPTIONS)
            ->where(
                $queryBuilder->expr()->eq('event', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        $result = [];
        foreach ($dates as $date) {
            if (is_string($date) && $date !== '' && !str_starts_with($date, '0000')) {
                $result[] = substr($date, 0, 10);
            }
        }

        return $result;
    }

    private function updateOccurrenceCount(int $pageUid, int $count): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_PAGES)->update(
            self::TABLE_PAGES,
            ['tx_golbevents_occurrences' => $count],
            ['uid' => $pageUid],
            ['tx_golbevents_occurrences' => Connection::PARAM_INT, 'uid' => Connection::PARAM_INT]
        );
    }
}
