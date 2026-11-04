<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Routing;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Imports the shipped route enhancer configuration into the site of the test
 * instance and checks that the plugins generate and resolve speaking URLs.
 * The Event list (two occurrences per page) and the Event calendar sit on
 * the root page (slug "/"). The fixture (see EventControllerTest) has the
 * Spring concert (tag php, category News) on 2099-03-10 and 2099-03-31, the
 * Summer festival (tag typo3) in July 2099, the Autumn walk in October 2099
 * and the Old meetup with two dates in 2001.
 */
#[CoversNothing]
final class RouteEnhancersTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    protected array $coreExtensionsToLoad = ['typo3/cms-fluid-styled-content'];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events/Tests/Functional/Routing/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventPlugins.csv');
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tt_content');
        $connection->insert('tt_content', [
            'uid' => 100,
            'pid' => 1,
            'colPos' => 0,
            'sorting' => 1,
            'CType' => 'golbevents_listview',
            'header' => 'List',
            'pages' => '2',
            'pi_flexform' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data>'
                . '<sheet index="sDEF"><language index="lDEF">'
                . '<field index="settings.itemsPerPage"><value index="vDEF">2</value></field>'
                . '<field index="settings.allowDemandOverwrite"><value index="vDEF">1</value></field>'
                . '</language></sheet></data></T3FlexForms>',
        ]);
        $connection->insert('tt_content', [
            'uid' => 101,
            'pid' => 1,
            'colPos' => 0,
            'sorting' => 2,
            'CType' => 'golbevents_calendarview',
            'header' => 'Calendar',
            'pages' => '2',
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
                'EXT:golb_events/Tests/Functional/Routing/Fixtures/Rendering.typoscript',
            ],
        ]);
    }

    private function request(string $path): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
    }

    /**
     * @return string[] titles of the listed occurrences in order
     */
    private function listedEvents(string $html): array
    {
        preg_match_all('/<h3 class="golbevents-event__title">\s*<a[^>]*>([^<]+)<\/a>/', $html, $matches);

        return $matches[1];
    }

    #[Test]
    public function paginationAndFilterLinksAreSpeakingUrls(): void
    {
        $html = (string)$this->request('/')->getBody();

        self::assertStringContainsString('href="/page/2"', $html);
        self::assertStringContainsString('href="/tag/php"', $html);
        self::assertStringContainsString('href="/category/News-1"', $html);
        self::assertStringNotContainsString('tx_golbevents_listview', $html);
        self::assertStringNotContainsString('cHash', $html);
    }

    #[Test]
    public function paginationRouteIsResolved(): void
    {
        $response = $this->request('/page/2');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['Summer festival', 'Autumn walk'], $this->listedEvents((string)$response->getBody()));
    }

    #[Test]
    public function modeRouteIsResolved(): void
    {
        self::assertSame(['Old meetup', 'Old meetup'], $this->listedEvents((string)$this->request('/past')->getBody()));
        self::assertSame(['Old meetup', 'Old meetup'], $this->listedEvents((string)$this->request('/all')->getBody()));
        self::assertSame(['Spring concert', 'Spring concert'], $this->listedEvents((string)$this->request('/all/page/2')->getBody()));
        self::assertSame(['Spring concert', 'Spring concert'], $this->listedEvents((string)$this->request('/upcoming')->getBody()));
    }

    #[Test]
    public function tagAndCategoryRoutesAreResolved(): void
    {
        self::assertSame(['Summer festival'], $this->listedEvents((string)$this->request('/tag/typo3')->getBody()));
        self::assertSame(['Spring concert', 'Spring concert'], $this->listedEvents((string)$this->request('/tag/php/page/1')->getBody()));
        self::assertSame(['Spring concert', 'Spring concert'], $this->listedEvents((string)$this->request('/category/News-1')->getBody()));
    }

    #[Test]
    public function calendarRouteIsResolvedAndNavigationLinksAreSpeakingUrls(): void
    {
        $response = $this->request('/2099/03');
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h3 class="golbevents-calendar__title">March 2099</h3>', $html);
        self::assertStringContainsString('href="/2099/02"', $html);
        self::assertStringContainsString('href="/2099/04"', $html);
        self::assertStringContainsString('<a class="golbevents-calendar__link" href="/events/spring-concert">Spring concert</a>', $html);
        self::assertStringNotContainsString('tx_golbevents_calendarview', $html);
        self::assertStringNotContainsString('cHash', $html);
    }

    #[Test]
    public function unknownRoutesAnswerWithNotFound(): void
    {
        self::assertSame(404, $this->request('/2099/13')->getStatusCode());
        self::assertSame(404, $this->request('/tag/no-such-tag')->getStatusCode());
        self::assertSame(404, $this->request('/someday')->getStatusCode());
    }

    #[Test]
    public function icalDownloadHasSpeakingUrl(): void
    {
        $html = (string)$this->request('/events/spring-concert')->getBody();

        self::assertStringContainsString('href="/events/spring-concert/event.ics"', $html);

        $response = $this->request('/events/spring-concert/event.ics');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString("SUMMARY:Spring concert\r\n", (string)$response->getBody());
    }
}
