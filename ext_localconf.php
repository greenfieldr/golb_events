<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEvents\Controller\EventController;
use Greenfieldr\GolbEvents\Controller\IcalController;
use Greenfieldr\GolbEvents\Hooks\DataHandlerHook;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

ExtensionUtility::configurePlugin('GolbEvents', 'ListView', [EventController::class => 'list'], []);
ExtensionUtility::configurePlugin('GolbEvents', 'UpcomingView', [EventController::class => 'upcoming'], []);
ExtensionUtility::configurePlugin('GolbEvents', 'CalendarView', [EventController::class => 'calendar'], []);
// iCal download of an event page, rendered by the page type "golbEventsIcal" (TypoScript)
ExtensionUtility::configurePlugin('GolbEvents', 'Ical', [IcalController::class => 'export'], []);

// Keep the generated occurrences in sync with the event pages
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['golb_events'] = DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['golb_events'] = DataHandlerHook::class;
