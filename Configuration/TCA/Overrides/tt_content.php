<?php

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

$boot = function () {
    $ll = 'LLL:EXT:golb_events/Resources/Private/Language/locallang_be.xlf:';
    $plugins = [
        'ListView' => ['listView', 'ext-golbevents-plugin-list-view', 'ListView'],
        'UpcomingView' => ['upcomingView', 'ext-golbevents-plugin-upcoming-view', 'UpcomingView'],
        'CalendarView' => ['calendarView', 'ext-golbevents-plugin-calendar-view', 'CalendarView'],
    ];

    foreach ($plugins as $pluginName => [$labelKey, $icon, $flexForm]) {
        $pluginSignature = ExtensionUtility::registerPlugin(
            'golb_events',
            $pluginName,
            $ll . 'tt_content.plugin.' . $labelKey . '.title',
            $icon,
            'Golb',
            $ll . 'tt_content.plugin.' . $labelKey . '.description',
            'FILE:EXT:golb_events/Configuration/FlexForms/' . $flexForm . '.xml'
        );

        $GLOBALS['TCA']['tt_content']['types'][$pluginSignature]['showitem'] = '
            --div--;core.form.tabs:general,
                --palette--;;general,
                --palette--;;headers,
                pages,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.plugin,
                pi_flexform,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
                --palette--;;frames,
                --palette--;;appearanceLinks,
            --div--;core.form.tabs:language,
                --palette--;;language,
            --div--;core.form.tabs:access,
                --palette--;;hidden,
                --palette--;;access,
            --div--;core.form.tabs:categories,
                categories,
            --div--;core.form.tabs:notes,
                rowDescription,
            --div--;core.form.tabs:extended,
        ';
    }
};
$boot();
unset($boot);
