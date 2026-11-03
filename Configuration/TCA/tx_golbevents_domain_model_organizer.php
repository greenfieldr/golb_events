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

return [
    'ctrl' => [
        'title' => $ll . 'tx_golbevents_domain_model_organizer',
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
            'default' => 'ext-golbevents-organizer',
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
                'foreign_table' => 'tx_golbevents_domain_model_organizer',
                'foreign_table_where' => 'AND {#tx_golbevents_domain_model_organizer}.{#pid}=###CURRENT_PID### AND {#tx_golbevents_domain_model_organizer}.{#sys_language_uid} IN (-1,0)',
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
            'label' => $ll . 'tx_golbevents_domain_model_organizer.name',
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
        'email' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_organizer.email',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'email',
                'default' => '',
            ],
        ],
        'phone' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_organizer.phone',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 50,
                'default' => '',
            ],
        ],
        'link' => [
            'exclude' => true,
            'label' => $ll . 'tx_golbevents_domain_model_organizer.link',
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
            'label' => $ll . 'tx_golbevents_domain_model_organizer.description',
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
        'contact' => [
            'showitem' => 'email, phone',
        ],
    ],
    'types' => [
        0 => [
            'showitem' => '
                --div--;core.form.tabs:general,
                    name, --palette--;;contact, link, description,
                --div--;core.form.tabs:language,
                    sys_language_uid, l10n_parent,
                --div--;core.form.tabs:access,
                    hidden,
            ',
        ],
    ],
];
