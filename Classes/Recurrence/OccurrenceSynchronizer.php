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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Keeps the generated occurrences (table tx_golbevents_domain_model_occurrence)
 * in sync with the event pages. Works on the live, default language records
 * through the database directly, so it can run from DataHandler hooks and
 * from the command line.
 */
class OccurrenceSynchronizer
{
    public const TABLE_PAGES = 'pages';
    public const TABLE_OCCURRENCES = 'tx_golbevents_domain_model_occurrence';
    public const TABLE_EXCEPTIONS = 'tx_golbevents_domain_model_exception';
    public const TABLE_LOCATIONS = 'tx_golbevents_domain_model_location';

    public const DEFAULT_HORIZON_YEARS = 2;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OccurrenceGenerator $generator,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly Context $context,
    ) {}

    /**
     * Regenerates the occurrences of one event page. Pages that are no event
     * (other doktype, translation, workspace version, deleted) lose their
     * occurrences. Returns the number of occurrences stored.
     */
    public function synchronizeEvent(int $pageUid): int
    {
        $row = $this->findLiveEventRow($pageUid);
        if ($row === null) {
            $this->deleteOccurrencesOfEvent($pageUid);
            $this->updateOccurrenceCount($pageUid, 0);

            return 0;
        }

        $schedule = EventSchedule::fromRecord(
            $row,
            $this->findLocationTimezone($this->toInt($row['tx_golbevents_location'] ?? 0)),
            $this->findExceptionDates($pageUid)
        );
        $occurrences = $schedule instanceof EventSchedule ? $this->generator->generate($schedule, $this->getHorizon()) : [];

        $this->deleteOccurrencesOfEvent($pageUid);
        if ($occurrences !== []) {
            $now = $this->context->getPropertyFromAspect('date', 'timestamp');
            $rows = [];
            foreach ($occurrences as $occurrence) {
                $rows[] = [
                    'pid' => $this->toInt($row['pid'] ?? 0),
                    'tstamp' => $now,
                    'crdate' => $now,
                    'event' => $pageUid,
                    'start' => $occurrence->start,
                    'end' => $occurrence->end,
                    'all_day' => (int)$occurrence->allDay,
                ];
            }
            $this->connectionPool->getConnectionForTable(self::TABLE_OCCURRENCES)->bulkInsert(
                self::TABLE_OCCURRENCES,
                $rows,
                ['pid', 'tstamp', 'crdate', 'event', 'start', 'end', 'all_day'],
                array_fill_keys(['pid', 'tstamp', 'crdate', 'event', 'start', 'end', 'all_day'], Connection::PARAM_INT)
            );
        }
        $this->updateOccurrenceCount($pageUid, count($occurrences));

        return count($occurrences);
    }

    /**
     * Regenerates the occurrences of every event page and removes occurrences
     * of records that are no events any more. Returns the number of events.
     */
    public function synchronizeAll(): int
    {
        $eventUids = $this->findAllLiveEventUids();
        foreach ($eventUids as $uid) {
            $this->synchronizeEvent($uid);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_OCCURRENCES);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->delete(self::TABLE_OCCURRENCES);
        if ($eventUids !== []) {
            $queryBuilder->where(
                $queryBuilder->expr()->notIn('event', $queryBuilder->createNamedParameter($eventUids, Connection::PARAM_INT_ARRAY))
            );
        }
        $queryBuilder->executeStatement();

        return count($eventUids);
    }

    /**
     * Regenerates the occurrences of all events held at a location, e.g.
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

    public function deleteOccurrencesOfEvent(int $pageUid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE_OCCURRENCES)
            ->delete(self::TABLE_OCCURRENCES, ['event' => $pageUid], ['event' => Connection::PARAM_INT]);
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
