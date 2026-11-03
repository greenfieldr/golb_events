<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Hooks;

use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Regenerates the occurrences of events when event pages, their excluded
 * dates or their locations are changed through the DataHandler.
 */
final readonly class DataHandlerHook
{
    public function __construct(
        private OccurrenceSynchronizer $synchronizer,
        private ConnectionPool $connectionPool,
    ) {}

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $eventUids = [];
        foreach (array_keys($dataHandler->datamap[OccurrenceSynchronizer::TABLE_PAGES] ?? []) as $id) {
            $eventUids[] = $this->resolveUid($dataHandler, (string)$id);
        }
        foreach (array_keys($dataHandler->datamap[OccurrenceSynchronizer::TABLE_EXCEPTIONS] ?? []) as $id) {
            $eventUids[] = $this->findEventOfException($this->resolveUid($dataHandler, (string)$id));
        }
        foreach ($this->uniqueUids($eventUids) as $uid) {
            $this->synchronizer->synchronizeEvent($uid);
        }

        foreach ($dataHandler->datamap[OccurrenceSynchronizer::TABLE_LOCATIONS] ?? [] as $id => $fields) {
            if (is_array($fields) && array_key_exists('timezone', $fields)) {
                $this->synchronizer->synchronizeLocation($this->resolveUid($dataHandler, (string)$id));
            }
        }
    }

    /**
     * @param array<string, mixed>|string|int $value
     */
    public function processCmdmap_postProcess(string $command, string $table, int|string $id, array|string|int $value, DataHandler $dataHandler): void
    {
        $id = MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : 0;
        if ($id <= 0) {
            return;
        }

        if ($table === OccurrenceSynchronizer::TABLE_PAGES) {
            $uid = match ($command) {
                'copy' => (int)($dataHandler->copyMappingArray[$table][$id] ?? 0),
                'undelete', 'move', 'version' => $id,
                default => 0,
            };
            if ($command === 'delete') {
                $this->synchronizer->deleteOccurrencesOfEvent($id);
            } elseif ($uid > 0) {
                $this->synchronizer->synchronizeEvent($uid);
            }

            return;
        }

        if ($table === OccurrenceSynchronizer::TABLE_EXCEPTIONS && in_array($command, ['delete', 'undelete', 'copy'], true)) {
            $uid = $command === 'copy' ? (int)($dataHandler->copyMappingArray[$table][$id] ?? 0) : $id;
            $eventUid = $this->findEventOfException($uid);
            if ($eventUid > 0) {
                $this->synchronizer->synchronizeEvent($eventUid);
            }
        }
    }

    private function resolveUid(DataHandler $dataHandler, string $id): int
    {
        if (MathUtility::canBeInterpretedAsInteger($id)) {
            return (int)$id;
        }

        return (int)($dataHandler->substNEWwithIDs[$id] ?? 0);
    }

    /**
     * The event page of an excluded date, also for deleted records.
     */
    private function findEventOfException(int $exceptionUid): int
    {
        if ($exceptionUid <= 0) {
            return 0;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(OccurrenceSynchronizer::TABLE_EXCEPTIONS);
        $queryBuilder->getRestrictions()->removeAll();
        $eventUid = $queryBuilder->select('event')
            ->from(OccurrenceSynchronizer::TABLE_EXCEPTIONS)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($exceptionUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        return is_numeric($eventUid) ? (int)$eventUid : 0;
    }

    /**
     * @param int[] $uids
     * @return int[]
     */
    private function uniqueUids(array $uids): array
    {
        return array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));
    }
}
