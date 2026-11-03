<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Shared helpers for tests that generate occurrences.
 */
abstract class AbstractOccurrenceTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    /**
     * Freezes "now" so the horizon of the generated occurrences is stable.
     */
    protected function freezeTime(string $now = '2026-01-01 12:00:00'): void
    {
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable($now, new \DateTimeZone('UTC'))));
    }

    protected function setUpBackendUserAndLanguage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @return list<array{start: int, end: int, all_day: int}>
     */
    protected function occurrencesOf(int $eventUid): array
    {
        $rows = $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_occurrence')
            ->select(['start', 'end', 'all_day'], 'tx_golbevents_domain_model_occurrence', ['event' => $eventUid], [], ['start' => 'ASC'])
            ->fetchAllAssociative();

        return array_map(
            static fn(array $row): array => [
                'start' => self::toInt($row['start']),
                'end' => self::toInt($row['end']),
                'all_day' => self::toInt($row['all_day']),
            ],
            $rows
        );
    }

    protected static function toInt(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int)$value;
    }

    /**
     * @return string[] the starts of the occurrences as ISO 8601 in the given
     *                  time zone, by default the server time zone (UTC in the
     *                  test instance), which applies to events without location
     */
    protected function occurrenceStartsOf(int $eventUid, ?string $timezone = null): array
    {
        $zone = new \DateTimeZone($timezone ?? date_default_timezone_get());

        return array_map(
            static fn(array $row): string => (new \DateTimeImmutable('@' . $row['start']))->setTimezone($zone)->format('c'),
            $this->occurrencesOf($eventUid)
        );
    }

    /**
     * A wall clock time of an event without location as ISO 8601 in the
     * server time zone, i.e. as occurrenceStartsOf() formats it.
     */
    protected static function serverIso(string $wallClock): string
    {
        return (new \DateTimeImmutable($wallClock))->format('c');
    }

    /**
     * Reads the occurrence count field of a page, also of hidden pages (new
     * pages created through the DataHandler are hidden by default).
     */
    protected function occurrenceCountFieldOf(int $pageUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        return self::toInt(
            $queryBuilder->select('tx_golbevents_occurrences')
                ->from('pages')
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchOne()
        );
    }

    protected function countAllOccurrences(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_golbevents_domain_model_occurrence')
            ->count('uid', 'tx_golbevents_domain_model_occurrence', []);
    }

    /**
     * @param array<string, int|string|null> $data
     * @param array<string, ParameterType|int|string> $types
     */
    protected function updatePage(int $uid, array $data, array $types = []): void
    {
        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', $data, ['uid' => $uid], $types + ['uid' => Connection::PARAM_INT]);
    }
}
