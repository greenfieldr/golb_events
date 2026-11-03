<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Controller;

use Greenfieldr\GolbEvents\Calendar\Calendar;
use Greenfieldr\GolbEvents\Calendar\CalendarBuilder;
use Greenfieldr\GolbEvents\Calendar\CalendarDay;
use Greenfieldr\GolbEvents\Controller\EventController;
use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the plugins through the frontend and checks the HTML output. The
 * fixture holds three events in 2099 (Spring concert on 2099-03-10 and
 * 2099-03-31, Summer festival 2099-07-01 to 03 all day, Autumn walk
 * 2099-10-05, cancelled) and one in 2001 (Old meetup, two dates).
 */
#[CoversClass(EventController::class)]
#[CoversClass(OccurrenceRepository::class)]
#[CoversClass(EventsDemand::class)]
#[CoversClass(CalendarBuilder::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(CalendarDay::class)]
#[CoversClass(Occurrence::class)]
final class EventControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    protected array $coreExtensionsToLoad = ['typo3/cms-fluid-styled-content'];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'enforceValidation' => false,
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventPlugins.csv');
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:golb/Configuration/TypoScript/constants.typoscript',
                'EXT:golb_events/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:golb/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events/Tests/Functional/Controller/Fixtures/TypoScript/Rendering.typoscript',
            ],
        ]);
    }

    /**
     * Creates a plugin content element on the root page.
     *
     * @param array<string, string|int> $settings FlexForm settings
     * @param int[] $categories uids of categories selected in the plugin
     */
    private function createPlugin(string $cType, string $pages, array $settings = [], array $categories = []): void
    {
        $fields = '';
        foreach ($settings as $name => $value) {
            $fields .= sprintf(
                '<field index="settings.%s"><value index="vDEF">%s</value></field>',
                htmlspecialchars($name, ENT_QUOTES),
                htmlspecialchars((string)$value, ENT_QUOTES)
            );
        }
        $flexForm = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data>'
            . '<sheet index="sDEF"><language index="lDEF">' . $fields . '</language></sheet>'
            . '</data></T3FlexForms>';

        $connectionPool = $this->get(ConnectionPool::class);
        $connectionPool->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 100,
            'pid' => 1,
            'colPos' => 0,
            'sorting' => 1,
            'CType' => $cType,
            'header' => 'Plugin',
            'pages' => $pages,
            'pi_flexform' => $flexForm,
            'categories' => count($categories),
        ]);
        foreach ($categories as $sorting => $categoryUid) {
            $connectionPool->getConnectionForTable('sys_category_record_mm')->insert('sys_category_record_mm', [
                'uid_local' => $categoryUid,
                'uid_foreign' => 100,
                'tablenames' => 'tt_content',
                'fieldname' => 'categories',
                'sorting' => $sorting + 1,
                'sorting_foreign' => $sorting + 1,
            ]);
        }
    }

    /**
     * @param array<string, int|string> $queryParameters flat parameter names, e.g. "tx_golbevents_listview[demand][tag]"
     */
    private function render(array $queryParameters = []): string
    {
        $request = (new InternalRequest())->withPageId(1);
        foreach ($queryParameters as $name => $value) {
            $request = $request->withQueryParameter($name, $value);
        }

        return (string)$this->executeFrontendSubRequest($request)->getBody();
    }

    private function countEvents(string $html): int
    {
        return substr_count($html, '<article class="golbevents-event');
    }

    #[Test]
    public function listViewRendersUpcomingOccurrencesChronologically(): void
    {
        $this->createPlugin('golbevents_listview', '2');

        $html = $this->render();

        self::assertSame(4, $this->countEvents($html));
        self::assertSame(2, substr_count($html, 'Spring concert'), 'two dates of the same event');
        self::assertStringContainsString('Summer festival', $html);
        self::assertStringContainsString('Autumn walk', $html);
        self::assertStringNotContainsString('Old meetup', $html);
        self::assertLessThan(strpos($html, 'Summer festival'), strpos($html, 'Spring concert'));
        self::assertLessThan(strpos($html, 'Autumn walk'), strpos($html, 'Summer festival'));
        self::assertStringNotContainsString('golb-pagination', $html);
    }

    #[Test]
    public function listViewShowsMessageWhenNoEventsExist(): void
    {
        $this->createPlugin('golbevents_listview', '7');

        $html = $this->render();

        self::assertStringContainsString('No events found.', $html);
        self::assertSame(0, $this->countEvents($html));
    }

    #[Test]
    public function listViewPaginatesAndFollowsPageArgument(): void
    {
        $this->createPlugin('golbevents_listview', '2', ['itemsPerPage' => 3]);

        $html = $this->render();

        self::assertSame(3, $this->countEvents($html));
        self::assertStringContainsString('golb-pagination', $html);
        self::assertStringNotContainsString('Autumn walk', $html);
        self::assertStringContainsString('tx_golbevents_listview%5Bdemand%5D%5BcurrentPage%5D=2', $html);

        $html = $this->render(['tx_golbevents_listview[demand][currentPage]' => 2]);

        self::assertSame(1, $this->countEvents($html));
        self::assertStringContainsString('Autumn walk', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    #[Test]
    public function listViewShowsPastOccurrencesMostRecentFirst(): void
    {
        $this->createPlugin('golbevents_listview', '2', ['mode' => 'past']);

        $html = $this->render();

        self::assertSame(2, $this->countEvents($html));
        self::assertSame(2, substr_count($html, 'Old meetup'));
        self::assertLessThan(strpos($html, '04.05.2001'), strpos($html, '11.05.2001'));
    }

    #[Test]
    public function listViewAppliesSortingAndFilterSettings(): void
    {
        $this->createPlugin('golbevents_listview', '2', [
            'mode' => 'all',
            'sorting' => 'title',
            'sortingDirection' => 'DESC',
            'exclude' => '6',
            'topPosts' => 'nonTopPosts',
        ]);

        $html = $this->render();

        self::assertSame(2, $this->countEvents($html));
        self::assertLessThan(strpos($html, 'Autumn walk'), strpos($html, 'Summer festival'));
        self::assertStringNotContainsString('Spring concert', $html);
        self::assertStringNotContainsString('Old meetup', $html);
    }

    #[Test]
    public function listViewFiltersByDateRangeSettings(): void
    {
        $this->createPlugin('golbevents_listview', '2', [
            'mode' => 'range',
            'from' => (new \DateTimeImmutable('2099-03-20', new \DateTimeZone('UTC')))->getTimestamp(),
            'to' => (new \DateTimeImmutable('2099-07-02', new \DateTimeZone('UTC')))->getTimestamp(),
        ]);

        $html = $this->render();

        self::assertSame(2, $this->countEvents($html));
        self::assertStringContainsString('31.03.2099', $html);
        self::assertStringContainsString('Summer festival', $html);
        self::assertStringNotContainsString('10.03.2099', $html);
        self::assertStringNotContainsString('Autumn walk', $html);
    }

    #[Test]
    public function listViewFiltersByCategoriesOfThePlugin(): void
    {
        $this->createPlugin('golbevents_listview', '2', [], [1]);

        $html = $this->render();

        self::assertSame(2, $this->countEvents($html));
        self::assertSame(2, substr_count($html, 'Spring concert'));
        self::assertStringNotContainsString('Summer festival', $html);
    }

    #[Test]
    public function upcomingViewRespectsLimitAndTopPostsSetting(): void
    {
        $this->createPlugin('golbevents_upcomingview', '2', ['limit' => 1]);

        $html = $this->render();
        self::assertSame(1, $this->countEvents($html));
        self::assertStringContainsString('10.03.2099', $html);
        self::assertStringNotContainsString('golb-pagination', $html);

        $this->get(ConnectionPool::class)->getConnectionForTable('tt_content')->delete('tt_content', ['uid' => 100]);
        $this->createPlugin('golbevents_upcomingview', '2', ['limit' => 0, 'topPosts' => 'topPosts']);

        $html = $this->render();
        self::assertSame(2, $this->countEvents($html));
        self::assertSame(2, substr_count($html, 'Spring concert'));
    }

    #[Test]
    public function demandArgumentsFilterEventsWhenOverwriteIsAllowed(): void
    {
        $this->createPlugin('golbevents_listview', '2', ['allowDemandOverwrite' => 1]);

        $html = $this->render(['tx_golbevents_listview[demand][tag]' => 2]);
        self::assertSame(1, $this->countEvents($html));
        self::assertStringContainsString('Summer festival', $html);

        $html = $this->render([
            'tx_golbevents_listview[demand][mode]' => 'range',
            'tx_golbevents_listview[demand][from]' => '2099-10-01',
            'tx_golbevents_listview[demand][to]' => '2099-10-31',
        ]);
        self::assertSame(1, $this->countEvents($html));
        self::assertStringContainsString('Autumn walk', $html);
    }

    #[Test]
    public function demandArgumentsAreIgnoredWhenOverwriteIsNotAllowed(): void
    {
        $this->createPlugin('golbevents_listview', '2', ['allowDemandOverwrite' => 0]);

        $html = $this->render(['tx_golbevents_listview[demand][tag]' => 2]);

        self::assertSame(4, $this->countEvents($html));
    }

    #[Test]
    public function occurrencePartialRendersDatesLocationOrganizerAndFlags(): void
    {
        $this->createPlugin('golbevents_listview', '2', ['mode' => 'all']);

        $html = $this->render();

        // Timed event: date, start and end time; the server time zone is UTC in the test instance
        self::assertStringContainsString('10.03.2099 19:30', $html);
        self::assertStringContainsString('21:00', $html);
        self::assertStringContainsString('datetime="2099-03-10T19:30:00+00:00"', $html);
        // All day event over three days
        self::assertStringContainsString('01.07.2099', $html);
        self::assertStringContainsString('03.07.2099', $html);
        self::assertStringContainsString('All day', $html);
        self::assertStringContainsString('datetime="2099-07-01"', $html);
        // Cancelled, location record, free text location and organizer, meta data
        self::assertStringContainsString('golbevents-event--cancelled', $html);
        self::assertStringContainsString('Cancelled', $html);
        self::assertStringContainsString('Location: Carnegie Hall', $html);
        self::assertStringContainsString('Location: Town hall', $html);
        self::assertStringContainsString('Organizer: Golb Society', $html);
        self::assertStringContainsString('A concert.', $html);
        self::assertMatchesRegularExpression('/Categories:\s+News/', $html);
        self::assertMatchesRegularExpression('/Tags:\s+php/', $html);
        self::assertStringContainsString('href="/events/spring-concert"', $html);
    }

    #[Test]
    public function calendarViewRendersTheRequestedMonthWithOccurrencesAndNavigation(): void
    {
        $this->createPlugin('golbevents_calendarview', '2');

        $html = $this->render([
            'tx_golbevents_calendarview[year]' => 2099,
            'tx_golbevents_calendarview[month]' => 3,
        ]);

        self::assertStringContainsString('golbevents-calendar__title">March 2099', $html);
        self::assertSame(2, substr_count($html, 'Spring concert'));
        self::assertSame(2, substr_count($html, 'golbevents-calendar__day--has-events'));
        self::assertStringContainsString('19:30', $html);
        self::assertStringNotContainsString('Summer festival', $html);
        self::assertStringNotContainsString('golbevents-calendar__empty', $html);
        // Six weeks: 2099-03-01 is a Sunday, the grid starts on Monday 2099-02-23
        self::assertSame(6, substr_count($html, 'golbevents-calendar__week"'));
        self::assertSame(42, substr_count($html, '<td class="golbevents-calendar__day'));
        self::assertStringContainsString('>Mon<', $html);
        // Navigation
        self::assertStringContainsString('rel="prev" href="/?tx_golbevents_calendarview%5Baction%5D=calendar&amp;tx_golbevents_calendarview%5Bcontroller%5D=Event&amp;tx_golbevents_calendarview%5Bmonth%5D=2&amp;tx_golbevents_calendarview%5Byear%5D=2099', $html);
        self::assertStringContainsString('rel="next" href="/?tx_golbevents_calendarview%5Baction%5D=calendar&amp;tx_golbevents_calendarview%5Bcontroller%5D=Event&amp;tx_golbevents_calendarview%5Bmonth%5D=4&amp;tx_golbevents_calendarview%5Byear%5D=2099', $html);
    }

    #[Test]
    public function calendarViewShowsMultiDayEventsOnEveryDayAndAnEmptyMessage(): void
    {
        $this->createPlugin('golbevents_calendarview', '2');

        $html = $this->render([
            'tx_golbevents_calendarview[year]' => 2099,
            'tx_golbevents_calendarview[month]' => 7,
        ]);
        self::assertSame(3, substr_count($html, 'Summer festival'));
        self::assertStringNotContainsString('golbevents-calendar__time', $html, 'all day events have no time');

        $html = $this->render([
            'tx_golbevents_calendarview[year]' => 2099,
            'tx_golbevents_calendarview[month]' => 8,
        ]);
        self::assertSame(0, substr_count($html, 'golbevents-calendar__event '));
        self::assertStringContainsString('No events in this month.', $html);
    }

    #[Test]
    public function calendarViewFallsBackToTheCurrentMonthForInvalidArguments(): void
    {
        $this->createPlugin('golbevents_calendarview', '2');

        $html = $this->render([
            'tx_golbevents_calendarview[year]' => 2099,
            'tx_golbevents_calendarview[month]' => 13,
        ]);

        self::assertStringContainsString('golbevents-calendar__title">' . date('F Y'), $html);
        self::assertStringContainsString('golbevents-calendar__day--today', $html);
    }

    #[Test]
    public function calendarViewWeeksCanStartOnSunday(): void
    {
        $this->createPlugin('golbevents_calendarview', '2');
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:golb/Configuration/TypoScript/constants.typoscript',
                'EXT:golb_events/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:golb/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events/Configuration/TypoScript/setup.typoscript',
                'EXT:golb_events/Tests/Functional/Controller/Fixtures/TypoScript/Rendering.typoscript',
                'EXT:golb_events/Tests/Functional/Controller/Fixtures/TypoScript/SundayFirst.typoscript',
            ],
        ]);

        $html = $this->render([
            'tx_golbevents_calendarview[year]' => 2099,
            'tx_golbevents_calendarview[month]' => 3,
        ]);

        // 2099-03-01 is a Sunday: the grid starts with the month itself, five weeks
        self::assertSame(5, substr_count($html, 'golbevents-calendar__week"'));
        self::assertLessThan(strpos($html, '>Mon<'), strpos($html, '>Sun<'));
    }
}
