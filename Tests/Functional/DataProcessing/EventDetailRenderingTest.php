<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\DataProcessing;

use Greenfieldr\GolbEvents\DataProcessing\EventSchemaProcessor;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders event pages with the detail rendering lib.golbEvents.event. The
 * fixture (see EventControllerTest) has the Spring concert (page 3, two
 * dates in March 2099 at Carnegie Hall in New York, organized by the Golb
 * Society, category News, tag php), the all day Summer festival (page 4),
 * the cancelled Autumn walk (page 5), the Old meetup (page 6) in 2001 and
 * a normal page 7.
 */
#[CoversClass(EventSchemaProcessor::class)]
#[CoversClass(OccurrenceRepository::class)]
#[CoversClass(Event::class)]
#[CoversClass(Occurrence::class)]
final class EventDetailRenderingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    protected array $coreExtensionsToLoad = ['typo3/cms-fluid-styled-content'];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventPlugins.csv');
        $this->get(ConnectionPool::class)->getConnectionForTable('tt_content')->insert('tt_content', [
            'uid' => 200,
            'pid' => 3,
            'colPos' => 0,
            'sorting' => 1,
            'CType' => 'text',
            'header' => 'Body',
            'bodytext' => '<p>The programme of the concert.</p>',
        ]);
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
                'EXT:golb_events/Tests/Functional/DataProcessing/Fixtures/DetailRendering.typoscript',
            ],
        ]);
    }

    private function render(int $pageId): string
    {
        return (string)$this->executeFrontendSubRequest((new InternalRequest())->withPageId($pageId))->getBody();
    }

    #[Test]
    public function eventPageRendersTheArticleWithFactsContentAndStructuredData(): void
    {
        $html = $this->render(3);

        self::assertStringContainsString('<article class="golb-post golb-post--detail golbevents-event golbevents-event--detail golbevents-event--top">', $html);
        self::assertStringContainsString('<h1 class="golb-post__title golbevents-event__title">Spring concert</h1>', $html);
        self::assertStringContainsString('<span class="golb-flag golb-flag--top">Top event</span>', $html);
        // Facts: next date, location and organizer; the server time zone is UTC in the test instance
        self::assertStringContainsString('<dt>When</dt>', $html);
        self::assertStringContainsString('10.03.2099 19:30', $html);
        self::assertStringContainsString('21:00', $html);
        self::assertStringContainsString('<dt>Where</dt>', $html);
        self::assertStringContainsString('Carnegie Hall, New York', $html);
        self::assertStringContainsString('<dt>Organizer</dt>', $html);
        self::assertStringContainsString('Golb Society', $html);
        self::assertStringNotContainsString('This event has ended.', $html);
        // Actions
        self::assertStringContainsString('class="golbevents-event__action golbevents-event__action--ical" href="/events/spring-concert?type=1764"', $html);
        self::assertStringContainsString('Add to calendar', $html);
        // Abstract, content, details and terms
        self::assertStringContainsString('<p class="golb-post__abstract golb-post__abstract--lead">A concert.</p>', $html);
        self::assertStringContainsString('<p>The programme of the concert.</p>', $html);
        self::assertStringContainsString('<strong class="golbevents-location__name">Carnegie Hall</strong>', $html);
        self::assertStringContainsString('<br> New York', $html);
        self::assertStringContainsString('<p class="golbevents-organizer__name">Golb Society</p>', $html);
        self::assertStringContainsString('<span class="golb-term">News</span>', $html);
        self::assertStringContainsString('<span class="golb-term">php</span>', $html);
        // Structured data and stylesheets
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Event","name":"Spring concert"', $html);
        self::assertStringContainsString('Resources/Public/Css/golb.css', $html);
        self::assertStringContainsString('Resources/Public/Css/golb_events.css', $html);
    }

    #[Test]
    public function recurringEventListsItsUpcomingDates(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->update(
            'pages',
            ['tx_golbevents_recurrence_frequency' => 'weekly'],
            ['uid' => 3]
        );

        $html = $this->render(3);

        self::assertStringContainsString('Recurring event', $html);
        self::assertStringContainsString('<h2 class="golbevents-dates__title">Upcoming dates</h2>', $html);
        self::assertSame(2, substr_count($html, '<li class="golbevents-dates__item">'));
        self::assertStringContainsString('31.03.2099 22:30', $html);
    }

    #[Test]
    public function singleEventDoesNotListDates(): void
    {
        $html = $this->render(3);

        self::assertStringNotContainsString('golbevents-dates', $html);
        self::assertStringNotContainsString('Recurring event', $html);
    }

    #[Test]
    public function cancelledEventIsFlagged(): void
    {
        $html = $this->render(5);

        self::assertStringContainsString('golbevents-event--detail golbevents-event--cancelled">', $html);
        self::assertStringContainsString('<span class="golb-flag golb-flag--cancelled">Cancelled</span>', $html);
        self::assertStringContainsString('https://schema.org/EventCancelled', $html);
    }

    #[Test]
    public function pastEventShowsItsLastDateAndNoCalendarLink(): void
    {
        $html = $this->render(6);

        self::assertStringContainsString('11.05.2001', $html);
        self::assertStringContainsString('This event has ended.', $html);
        self::assertStringNotContainsString('Add to calendar', $html);
    }

    #[Test]
    public function ordinaryPageRendersNothing(): void
    {
        $html = $this->render(7);

        self::assertStringNotContainsString('<article', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
        self::assertStringContainsString('<div class="golb">', $html);
    }
}
