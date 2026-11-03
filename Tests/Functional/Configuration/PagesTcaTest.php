<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Configuration;

use Greenfieldr\Golb\Constants as GolbConstants;
use Greenfieldr\GolbEvents\Constants;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies the TCA of the event page type as it is after Golb and Golb
 * Events have been loaded.
 */
#[CoversNothing]
final class PagesTcaTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['greenfieldr/golb', 'greenfieldr/golb_events'];

    /**
     * Returns a value of $GLOBALS['TCA'] by its path, asserting that every
     * segment exists.
     */
    private function tca(string|int ...$path): mixed
    {
        $value = $GLOBALS['TCA'];
        foreach ($path as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value, 'Missing TCA key ' . implode('.', $path));
            $value = $value[$key];
        }

        return $value;
    }

    #[Test]
    public function eventDoktypeIsRegisteredAfterTheBlogPostDoktype(): void
    {
        $items = $this->tca('pages', 'columns', 'doktype', 'config', 'items');
        self::assertIsArray($items);
        $values = array_column($items, 'value');
        $eventPosition = array_search((string)Constants::EVENT_DOKTYPE, $values, true);
        $postPosition = array_search((string)GolbConstants::BLOG_POST_DOKTYPE, $values, true);

        self::assertIsInt($eventPosition);
        self::assertIsInt($postPosition);
        self::assertSame($postPosition + 1, $eventPosition);
        self::assertSame('apps-pagetree-event', $this->tca('pages', 'ctrl', 'typeicon_classes', Constants::EVENT_DOKTYPE));
        self::assertSame('apps-pagetree-event-hideinmenu', $this->tca('pages', 'ctrl', 'typeicon_classes', Constants::EVENT_DOKTYPE . '-hideinmenu'));
    }

    #[Test]
    public function eventTypeShowsGolbTabAndEventTabs(): void
    {
        $showitem = $this->tca('pages', 'types', Constants::EVENT_DOKTYPE, 'showitem');
        self::assertIsString($showitem);
        $postShowitem = $this->tca('pages', 'types', GolbConstants::BLOG_POST_DOKTYPE, 'showitem');
        self::assertIsString($postShowitem);

        self::assertStringContainsString('locallang_db.xlf:pages.golbTab', $showitem);
        self::assertStringContainsString('locallang_db.xlf:pages.eventTab', $showitem);
        self::assertStringContainsString('--palette--;;tx_golbevents_dates', $showitem);
        self::assertStringContainsString('--palette--;;tx_golbevents_recurrence', $showitem);
        self::assertStringContainsString('tx_golbevents_exceptions', $showitem);
        self::assertStringNotContainsString('tx_golbevents_occurrences', $showitem);
        self::assertStringNotContainsString('tx_golbevents_', $postShowitem);
    }

    #[Test]
    public function golbFieldsAreShownForEventsExceptTheArchivedFlag(): void
    {
        $shared = 'FIELD:doktype:IN:' . GolbConstants::BLOG_POST_DOKTYPE . ',' . Constants::EVENT_DOKTYPE;

        foreach (['tx_golb_publish_date', 'tx_golb_tags', 'tx_golb_related', 'tx_golb_top_post', 'tx_golb_author_image'] as $field) {
            self::assertSame($shared, $this->tca('pages', 'columns', $field, 'displayCond'), $field);
        }
        self::assertSame('FIELD:doktype:=:' . GolbConstants::BLOG_POST_DOKTYPE, $this->tca('pages', 'columns', 'tx_golb_archived', 'displayCond'));
    }

    #[Test]
    public function eventDatesAreNativeDatetimeColumnsSharedByTranslations(): void
    {
        self::assertSame('datetime', $this->tca('pages', 'columns', 'tx_golbevents_start', 'config', 'dbType'));
        self::assertSame('datetime', $this->tca('pages', 'columns', 'tx_golbevents_end', 'config', 'dbType'));
        self::assertSame('date', $this->tca('pages', 'columns', 'tx_golbevents_recurrence_until', 'config', 'dbType'));
        foreach (['tx_golbevents_start', 'tx_golbevents_end', 'tx_golbevents_recurrence_frequency', 'tx_golbevents_exceptions', 'tx_golbevents_location'] as $field) {
            self::assertSame('exclude', $this->tca('pages', 'columns', $field, 'l10n_mode'), $field);
        }
    }

    #[Test]
    public function recordTablesAreRegistered(): void
    {
        foreach (['location', 'organizer', 'exception', 'occurrence'] as $table) {
            self::assertIsArray($this->tca('tx_golbevents_domain_model_' . $table, 'columns'));
        }
        self::assertTrue($this->tca('tx_golbevents_domain_model_occurrence', 'ctrl', 'readOnly'));
        self::assertTrue($this->tca('tx_golbevents_domain_model_exception', 'ctrl', 'hideTable'));
    }
}
