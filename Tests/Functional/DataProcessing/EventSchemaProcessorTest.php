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
use Greenfieldr\GolbEvents\Domain\Repository\OccurrenceRepository;
use Greenfieldr\GolbEvents\Schema\EventSchemaBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders event pages with the processor in the page template. The fixture
 * (see EventControllerTest) has the Spring concert (page 3) with dates on
 * 2099-03-10 and 2099-03-31 at Carnegie Hall, the cancelled Autumn walk
 * (page 5), the Old meetup (page 6) in 2001 and a normal page 7.
 */
#[CoversClass(EventSchemaProcessor::class)]
#[CoversClass(EventSchemaBuilder::class)]
#[CoversClass(OccurrenceRepository::class)]
final class EventSchemaProcessorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/golb_events/Tests/Functional/Controller/Fixtures/Sites/' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventPlugins.csv');
        $this->setUpFrontendRootPage(1, [
            'setup' => ['EXT:golb_events/Tests/Functional/DataProcessing/Fixtures/Rendering.typoscript'],
        ]);
    }

    private function render(int $pageId): string
    {
        return (string)$this->executeFrontendSubRequest((new InternalRequest())->withPageId($pageId))->getBody();
    }

    #[Test]
    public function eventPageProvidesTheEventAndItsStructuredData(): void
    {
        $html = $this->render(3);

        self::assertStringContainsString('[event:Spring concert:Carnegie Hall]', $html);
        self::assertStringContainsString('[schema:2099-03-10T15:30:00-04:00:https://schema.org/EventScheduled]', $html);
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Event","name":"Spring concert","startDate":"2099-03-10T15:30:00-04:00","endDate":"2099-03-10T17:00:00-04:00","eventStatus":"https://schema.org/EventScheduled","url":"http://localhost/events/spring-concert","description":"A concert.","location":{"@type":"Place","name":"Carnegie Hall","address":{"@type":"PostalAddress","addressLocality":"New York"}},"organizer":{"@type":"Organization","name":"Golb Society"}}</script>', $html);
    }

    #[Test]
    public function processorProvidesTheNextAndTheUpcomingOccurrences(): void
    {
        $html = $this->render(3);

        self::assertStringContainsString('[next:2099-03-10]', $html);
        self::assertStringContainsString('[upcoming:2099-03-10;2099-03-31;]', $html);
    }

    #[Test]
    public function pastEventHasNoUpcomingOccurrences(): void
    {
        $html = $this->render(6);

        self::assertStringContainsString('[next:2001-05-11]', $html);
        self::assertStringContainsString('[upcoming:]', $html);
    }

    #[Test]
    public function cancelledEventIsMarkedAsCancelled(): void
    {
        $html = $this->render(5);

        self::assertStringContainsString('[event:Autumn walk:]', $html);
        self::assertStringContainsString('https://schema.org/EventCancelled', $html);
    }

    #[Test]
    public function pastEventUsesItsLastOccurrence(): void
    {
        $html = $this->render(6);

        self::assertStringContainsString('[schema:2001-05-11T18:00:00+00:00:', $html);
    }

    #[Test]
    public function ordinaryPageProvidesNoEventAndNoStructuredData(): void
    {
        $html = $this->render(7);

        self::assertStringContainsString('[event::]', $html);
        self::assertStringContainsString('[schema::]', $html);
        self::assertStringContainsString('[next:]', $html);
        self::assertStringContainsString('[upcoming:]', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
    }
}
