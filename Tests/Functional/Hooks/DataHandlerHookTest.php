<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Hooks;

use Greenfieldr\GolbEvents\Hooks\DataHandlerHook;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\GeneratedOccurrence;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceGenerator;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use Greenfieldr\GolbEvents\Tests\Functional\AbstractOccurrenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[CoversClass(DataHandlerHook::class)]
#[CoversClass(OccurrenceSynchronizer::class)]
#[CoversClass(OccurrenceGenerator::class)]
#[CoversClass(GeneratedOccurrence::class)]
#[CoversClass(EventSchedule::class)]
#[CoversClass(RecurrenceRuleBuilder::class)]
final class DataHandlerHookTest extends AbstractOccurrenceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventSite.csv');
        $this->freezeTime();
        $this->setUpBackendUserAndLanguage();
        $this->get(OccurrenceSynchronizer::class)->synchronizeAll();
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     * @param array<string, array<int|string, array<string, mixed>>> $cmdmap
     */
    private function process(array $datamap, array $cmdmap = []): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, $cmdmap);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        return $dataHandler;
    }

    #[Test]
    public function creatingAnEventPageGeneratesItsOccurrences(): void
    {
        $dataHandler = $this->process([
            'pages' => [
                'NEW1' => [
                    'pid' => 2,
                    'title' => 'New event',
                    'doktype' => 42,
                    'tx_golbevents_start' => '2026-09-01T10:00:00',
                    'tx_golbevents_end' => '2026-09-01T12:00:00',
                    'tx_golbevents_recurrence_frequency' => 'daily',
                    'tx_golbevents_recurrence_end' => 'count',
                    'tx_golbevents_recurrence_count' => 3,
                ],
            ],
        ]);
        $uid = (int)$dataHandler->substNEWwithIDs['NEW1'];

        self::assertSame(
            [self::serverIso('2026-09-01 10:00:00'), self::serverIso('2026-09-02 10:00:00'), self::serverIso('2026-09-03 10:00:00')],
            $this->occurrenceStartsOf($uid)
        );
        self::assertSame(3, $this->occurrenceCountFieldOf($uid));
    }

    #[Test]
    public function changingTheDatesRegeneratesTheOccurrences(): void
    {
        $this->process(['pages' => [3 => ['tx_golbevents_start' => '2026-05-05T20:00:00', 'tx_golbevents_end' => '2026-05-05T22:00:00']]]);

        self::assertSame(['2026-05-05T20:00:00-04:00'], $this->occurrenceStartsOf(3, 'America/New_York'));
    }

    #[Test]
    public function changingThePageTypeRemovesTheOccurrences(): void
    {
        $this->process(['pages' => [3 => ['doktype' => 1]]]);

        self::assertSame([], $this->occurrencesOf(3));
        self::assertSame(0, $this->occurrenceCountFieldOf(3));
    }

    #[Test]
    public function addingAnExcludedDateRemovesTheOccurrence(): void
    {
        self::assertContains(self::serverIso('2026-06-15 18:00:00'), $this->occurrenceStartsOf(4));

        $this->process([
            'tx_golbevents_domain_model_exception' => ['NEW1' => ['pid' => 2, 'event' => 4, 'date' => '2026-06-15T00:00:00']],
        ]);

        self::assertNotContains(self::serverIso('2026-06-15 18:00:00'), $this->occurrenceStartsOf(4));
        self::assertSame(31, $this->occurrenceCountFieldOf(4));
    }

    #[Test]
    public function deletingAnExcludedDateRestoresTheOccurrence(): void
    {
        // Exception 1 excludes 2026-06-08, a Monday in a skipped week; move it to a counted week first
        $this->process(['tx_golbevents_domain_model_exception' => [1 => ['date' => '2026-06-15T00:00:00']]]);
        self::assertNotContains(self::serverIso('2026-06-15 18:00:00'), $this->occurrenceStartsOf(4));

        $this->process([], ['tx_golbevents_domain_model_exception' => [1 => ['delete' => 1]]]);

        self::assertContains(self::serverIso('2026-06-15 18:00:00'), $this->occurrenceStartsOf(4));
    }

    #[Test]
    public function changingTheTimezoneOfALocationRegeneratesItsEvents(): void
    {
        $this->process(['tx_golbevents_domain_model_location' => [1 => ['timezone' => 'Europe/Berlin']]]);

        self::assertSame(['2026-05-04T19:30:00+02:00'], $this->occurrenceStartsOf(3, 'Europe/Berlin'));
    }

    #[Test]
    public function changingOtherLocationFieldsLeavesTheOccurrencesAlone(): void
    {
        $before = $this->occurrencesOf(3);

        $this->process(['tx_golbevents_domain_model_location' => [1 => ['name' => 'Renamed']]]);

        self::assertSame($before, $this->occurrencesOf(3));
    }

    #[Test]
    public function deletingAnEventPageRemovesItsOccurrences(): void
    {
        $this->process([], ['pages' => [3 => ['delete' => 1]]]);

        self::assertSame([], $this->occurrencesOf(3));
    }

    #[Test]
    public function copyingAnEventPageGeneratesOccurrencesForTheCopy(): void
    {
        $dataHandler = $this->process([], ['pages' => [3 => ['copy' => 2]]]);
        $copyUid = (int)($dataHandler->copyMappingArray_merged['pages'][3] ?? 0);

        self::assertGreaterThan(0, $copyUid);
        self::assertSame(['2026-05-04T19:30:00-04:00'], $this->occurrenceStartsOf($copyUid, 'America/New_York'));
        self::assertSame(['2026-05-04T19:30:00-04:00'], $this->occurrenceStartsOf(3, 'America/New_York'), 'the original keeps its own');
    }

    #[Test]
    public function savingAnOrdinaryPageDoesNothing(): void
    {
        $before = $this->countAllOccurrences();

        $this->process(['pages' => [2 => ['title' => 'Events renamed']]]);

        self::assertSame($before, $this->countAllOccurrences());
        self::assertSame([], $this->occurrencesOf(2));
    }
}
