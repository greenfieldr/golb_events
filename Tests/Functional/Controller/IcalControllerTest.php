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

use Greenfieldr\GolbEvents\Controller\IcalController;
use Greenfieldr\GolbEvents\Export\IcalBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Requests the iCal page type of event pages (fixture see EventControllerTest).
 */
#[CoversClass(IcalController::class)]
#[CoversClass(IcalBuilder::class)]
final class IcalControllerTest extends FunctionalTestCase
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
        $this->setUpFrontendRootPage(1, [
            'constants' => [
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

    private function request(int $pageId): ResponseInterface
    {
        return $this->executeFrontendSubRequest((new InternalRequest())->withPageId($pageId)->withQueryParameter('type', 1764));
    }

    #[Test]
    public function eventPageIsDeliveredAsCalendarFile(): void
    {
        $response = $this->request(3);
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('attachment; filename="spring-concert.ics"', $response->getHeaderLine('Content-Disposition'));
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $body);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $body);
        self::assertStringContainsString("UID:golb-event-3@localhost\r\n", $body);
        self::assertStringContainsString("DTSTART;TZID=America/New_York:20990310T193000\r\n", $body);
        self::assertStringContainsString("SUMMARY:Spring concert\r\n", $body);
        self::assertStringContainsString("LOCATION:Carnegie Hall\\, New York\r\n", $body);
        self::assertStringContainsString("URL:http://localhost/events/spring-concert\r\n", $body);
        self::assertStringNotContainsString('<html', $body);
    }

    #[Test]
    public function cancelledAllDayEventsAreExportedAccordingly(): void
    {
        $body = (string)$this->request(4)->getBody();
        self::assertStringContainsString("DTSTART;VALUE=DATE:20990701\r\n", $body);
        self::assertStringContainsString("DTEND;VALUE=DATE:20990704\r\n", $body);

        $body = (string)$this->request(5)->getBody();
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $body);
    }

    #[Test]
    public function ordinaryPageAnswersWithNotFound(): void
    {
        $response = $this->request(7);

        self::assertSame(404, $response->getStatusCode());
    }
}
