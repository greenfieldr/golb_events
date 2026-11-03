<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

defined('TYPO3') || die();

$ll = 'LLL:EXT:golb_events/Resources/Private/Language/locallang_db.xlf:';

$timezoneItems = [['label' => $ll . 'tx_golbevents_domain_model_location.timezone.server', 'value' => '']];
foreach (\DateTimeZone::listIdentifiers() as $timezone) {
    $timezoneItems[] = ['label' => $timezone, 'value' => $timezone];
}

return [
    'ctrl' => [
        'title' => $ll . 'tx_golbevents_domain_model_location',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'default_sortby' => 'ORDER BY name',
        'delete' => 'deleted',
        'editlock' => 'editlock',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => [
            'default' => 'ext-golbevents-location',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'pid' => [
            'label' => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'datetime', 'searchable' => false,
            ],
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'datetime', 'searchable' => false,
            ],
        ],
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_golbevents_domain_model_location',
                'foreign_table_where' => 'AND {#tx_golbevents_domain_model_location}.{#pid}=###CURRENT_PID### AND {#tx_golbevents_domain_model_location}.{#sys_language_uid} IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_source' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'name' => [
            'label' => $ll . 'tx_golbevents_domain_model_location.name',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'required' => true,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'street' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.street',
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
        'zip' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.zip',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 20,
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'city' => [
            'label' => $ll . 'tx_golbevents_domain_model_location.city',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'required' => true,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'country' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.country',
            'config' => [
                'type' => 'country',
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'latitude' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.latitude',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'nullable' => true,
                'default' => null,
                'range' => [
                    'lower' => -90,
                    'upper' => 90,
                ],
            ],
        ],
        'longitude' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.longitude',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'nullable' => true,
                'default' => null,
                'range' => [
                    'lower' => -180,
                    'upper' => 180,
                ],
            ],
        ],
        'timezone' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.timezone',
            'description' => $ll . 'tx_golbevents_domain_model_location.timezone.description',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => $timezoneItems,
                'default' => '',
            ],
        ],
        'link' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.link',
            'config' => [
                'type' => 'link',
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_location.description',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'default' => '',
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
                'searchable' => false,
            ],
        ],
    ],
    'palettes' => [
        'address' => [
            'showitem' => 'street, --linebreak--, zip, city, --linebreak--, country',
        ],
        'geo' => [
            'showitem' => 'latitude, longitude',
        ],
    ],
    'types' => [
        0 => [
            'showitem' => '
                --div--;core.form.tabs:general,
                    name, --palette--;;address, --palette--;;geo, timezone, link, description,
                --div--;core.form.tabs:language,
                    sys_language_uid, l10n_parent,
                --div--;core.form.tabs:access,
                    hidden,
            ',
        ],
    ],
];
