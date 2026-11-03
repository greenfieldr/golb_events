<?php

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\Golb\Constants as GolbConstants;
use Greenfieldr\GolbEvents\Constants;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$boot = function () {
    $ll = 'LLL:EXT:golb_events/Resources/Private/Language/locallang_db.xlf:';
    $doktype = (string)Constants::EVENT_DOKTYPE;

    ExtensionManagementUtility::addTcaSelectItem(
        'pages',
        'doktype',
        [
            'label' => 'LLL:EXT:golb_events/Resources/Private/Language/locallang_be.xlf:doktype.event',
            'value' => $doktype,
            'icon' => 'apps-pagetree-event',
        ],
        (string)GolbConstants::BLOG_POST_DOKTYPE,
        'after'
    );

    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TCA']['pages'],
        [
            'ctrl' => [
                'typeicon_classes' => [
                    $doktype => 'apps-pagetree-event',
                    $doktype . '-hideinmenu' => 'apps-pagetree-event-hideinmenu',
                ],
            ],
            'types' => [
                // Golb's tab is already part of the default type at this point
                $doktype => $GLOBALS['TCA']['pages']['types'][PageRepository::DOKTYPE_DEFAULT],
            ],
        ]
    );

    // Golb shows its fields for blog posts only. Events use the same
    // metadata (publish date, tags, related pages, top post, author image),
    // except the archived flag, which is derived from the event dates.
    $sharedGolbFields = [
        'tx_golb_related',
        'tx_golb_content_elements',
        'tx_golb_author_image',
        'tx_golb_subpages',
        'tx_golb_publish_date',
        'tx_golb_tags',
        'tx_golb_top_post',
    ];
    foreach ($sharedGolbFields as $field) {
        if (isset($GLOBALS['TCA']['pages']['columns'][$field])) {
            $GLOBALS['TCA']['pages']['columns'][$field]['displayCond']
                = 'FIELD:doktype:IN:' . GolbConstants::BLOG_POST_DOKTYPE . ',' . Constants::EVENT_DOKTYPE;
        }
    }

    $frequencyCondition = 'FIELD:tx_golbevents_recurrence_frequency:!=:' . Event::FREQUENCY_NONE;

    $tempColumns = [
        'tx_golbevents_start' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_start',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'datetime',
                'format' => 'datetime',
                'required' => true,
                'nullable' => true,
            ],
        ],
        'tx_golbevents_end' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_end',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'datetime',
                'format' => 'datetime',
                'nullable' => true,
            ],
        ],
        'tx_golbevents_all_day' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_all_day',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'tx_golbevents_cancelled' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_cancelled',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'tx_golbevents_location' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_location',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_golbevents_location.freeText', 'value' => 0],
                ],
                'foreign_table' => 'tx_golbevents_domain_model_location',
                'foreign_table_where' => ' AND {#tx_golbevents_domain_model_location}.{#sys_language_uid} IN (-1,0) ORDER BY tx_golbevents_domain_model_location.name',
                'default' => 0,
            ],
        ],
        'tx_golbevents_location_text' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_location_text',
            'displayCond' => 'FIELD:tx_golbevents_location:=:0',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'tx_golbevents_organizer' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_organizer',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_golbevents_organizer.freeText', 'value' => 0],
                ],
                'foreign_table' => 'tx_golbevents_domain_model_organizer',
                'foreign_table_where' => ' AND {#tx_golbevents_domain_model_organizer}.{#sys_language_uid} IN (-1,0) ORDER BY tx_golbevents_domain_model_organizer.name',
                'default' => 0,
            ],
        ],
        'tx_golbevents_organizer_text' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_organizer_text',
            'displayCond' => 'FIELD:tx_golbevents_organizer:=:0',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'tx_golbevents_link' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_link',
            'config' => [
                'type' => 'link',
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'tx_golbevents_recurrence_frequency' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_frequency',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_frequency.none', 'value' => Event::FREQUENCY_NONE],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_frequency.daily', 'value' => Event::FREQUENCY_DAILY],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_frequency.weekly', 'value' => Event::FREQUENCY_WEEKLY],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_frequency.monthly', 'value' => Event::FREQUENCY_MONTHLY],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_frequency.yearly', 'value' => Event::FREQUENCY_YEARLY],
                ],
                'default' => Event::FREQUENCY_NONE,
            ],
        ],
        'tx_golbevents_recurrence_interval' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_interval',
            'l10n_mode' => 'exclude',
            'displayCond' => $frequencyCondition,
            'config' => [
                'type' => 'number',
                'default' => 1,
                'range' => [
                    'lower' => 1,
                ],
            ],
        ],
        'tx_golbevents_recurrence_weekdays' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_weekdays',
            'l10n_mode' => 'exclude',
            'displayCond' => 'FIELD:tx_golbevents_recurrence_frequency:=:' . Event::FREQUENCY_WEEKLY,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => [
                    ['label' => $ll . 'weekday.monday', 'value' => 'MO'],
                    ['label' => $ll . 'weekday.tuesday', 'value' => 'TU'],
                    ['label' => $ll . 'weekday.wednesday', 'value' => 'WE'],
                    ['label' => $ll . 'weekday.thursday', 'value' => 'TH'],
                    ['label' => $ll . 'weekday.friday', 'value' => 'FR'],
                    ['label' => $ll . 'weekday.saturday', 'value' => 'SA'],
                    ['label' => $ll . 'weekday.sunday', 'value' => 'SU'],
                ],
                'minitems' => 0,
                'maxitems' => 7,
                'default' => '',
            ],
        ],
        'tx_golbevents_recurrence_monthly_mode' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_monthly_mode',
            'l10n_mode' => 'exclude',
            'displayCond' => 'FIELD:tx_golbevents_recurrence_frequency:=:' . Event::FREQUENCY_MONTHLY,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_monthly_mode.dayOfMonth', 'value' => Event::MONTHLY_MODE_DAY_OF_MONTH],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_monthly_mode.weekday', 'value' => Event::MONTHLY_MODE_WEEKDAY],
                ],
                'default' => Event::MONTHLY_MODE_DAY_OF_MONTH,
            ],
        ],
        'tx_golbevents_recurrence_end' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_end',
            'l10n_mode' => 'exclude',
            'displayCond' => $frequencyCondition,
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_end.never', 'value' => Event::RECURRENCE_END_NEVER],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_end.until', 'value' => Event::RECURRENCE_END_UNTIL],
                    ['label' => $ll . 'pages.tx_golbevents_recurrence_end.count', 'value' => Event::RECURRENCE_END_COUNT],
                ],
                'default' => Event::RECURRENCE_END_NEVER,
            ],
        ],
        'tx_golbevents_recurrence_until' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_until',
            'l10n_mode' => 'exclude',
            'displayCond' => [
                'AND' => [
                    $frequencyCondition,
                    'FIELD:tx_golbevents_recurrence_end:=:' . Event::RECURRENCE_END_UNTIL,
                ],
            ],
            'config' => [
                'type' => 'datetime',
                'dbType' => 'date',
                'format' => 'date',
                'nullable' => true,
            ],
        ],
        'tx_golbevents_recurrence_count' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_recurrence_count',
            'l10n_mode' => 'exclude',
            'displayCond' => [
                'AND' => [
                    $frequencyCondition,
                    'FIELD:tx_golbevents_recurrence_end:=:' . Event::RECURRENCE_END_COUNT,
                ],
            ],
            'config' => [
                'type' => 'number',
                'default' => 0,
                'range' => [
                    'lower' => 0,
                ],
            ],
        ],
        'tx_golbevents_exceptions' => [
            'exclude' => true,
            'label' => $ll . 'pages.tx_golbevents_exceptions',
            'l10n_mode' => 'exclude',
            'displayCond' => $frequencyCondition,
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_golbevents_domain_model_exception',
                'foreign_field' => 'event',
                'foreign_sortby' => 'date',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => true,
                    'levelLinksPosition' => 'bottom',
                    'useSortable' => false,
                ],
            ],
        ],
        // Generated occurrences, not edited in the backend (no showitem entry).
        'tx_golbevents_occurrences' => [
            'label' => $ll . 'pages.tx_golbevents_occurrences',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_golbevents_domain_model_occurrence',
                'foreign_field' => 'event',
                'foreign_sortby' => 'start',
                'maxitems' => 9999,
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);

    $GLOBALS['TCA']['pages']['palettes']['tx_golbevents_dates'] = [
        'label' => $ll . 'pages.palette.dates',
        'showitem' => 'tx_golbevents_start, tx_golbevents_end, --linebreak--, tx_golbevents_all_day, tx_golbevents_cancelled',
    ];
    $GLOBALS['TCA']['pages']['palettes']['tx_golbevents_location'] = [
        'label' => $ll . 'pages.palette.location',
        'showitem' => 'tx_golbevents_location, tx_golbevents_location_text',
    ];
    $GLOBALS['TCA']['pages']['palettes']['tx_golbevents_organizer'] = [
        'label' => $ll . 'pages.palette.organizer',
        'showitem' => 'tx_golbevents_organizer, tx_golbevents_organizer_text',
    ];
    $GLOBALS['TCA']['pages']['palettes']['tx_golbevents_recurrence'] = [
        'label' => $ll . 'pages.palette.recurrence',
        'showitem' => 'tx_golbevents_recurrence_frequency, tx_golbevents_recurrence_interval, --linebreak--, '
            . 'tx_golbevents_recurrence_weekdays, tx_golbevents_recurrence_monthly_mode, --linebreak--, '
            . 'tx_golbevents_recurrence_end, tx_golbevents_recurrence_until, tx_golbevents_recurrence_count',
    ];

    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        '--div--;' . $ll . 'pages.eventTab, '
            . '--palette--;;tx_golbevents_dates, '
            . '--palette--;;tx_golbevents_location, '
            . '--palette--;;tx_golbevents_organizer, '
            . 'tx_golbevents_link, '
            . '--div--;' . $ll . 'pages.recurrenceTab, '
            . '--palette--;;tx_golbevents_recurrence, '
            . 'tx_golbevents_exceptions',
        $doktype
    );
};
$boot();
unset($boot);
