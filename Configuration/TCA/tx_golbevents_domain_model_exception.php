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

/*
 * A date excluded from the recurrence of an event. The records are children
 * of the event page (field tx_golbevents_exceptions) and are not listed on
 * their own.
 */
return [
    'ctrl' => [
        'title' => $ll . 'tx_golbevents_domain_model_exception',
        'label' => 'date',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'default_sortby' => 'ORDER BY date',
        'delete' => 'deleted',
        'hideTable' => true,
        'typeicon_classes' => [
            'default' => 'ext-golbevents-exception',
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
        'event' => [
            'label' => $ll . 'tx_golbevents_domain_model_exception.event',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'pages',
                'default' => 0,
            ],
        ],
        'date' => [
            'label' => $ll . 'tx_golbevents_domain_model_exception.date',
            'config' => [
                'type' => 'datetime',
                'dbType' => 'date',
                'format' => 'date',
                'required' => true,
                'nullable' => true,
            ],
        ],
    ],
    'types' => [
        0 => [
            'showitem' => 'date',
        ],
    ],
];
