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
 * A single occurrence of an event: the real point in time (UTC) of one
 * date of a recurring or non recurring event. The records are generated
 * from the event page and are read only in the backend.
 */
return [
    'ctrl' => [
        'title' => $ll . 'tx_golbevents_domain_model_occurrence',
        'label' => 'start',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'ORDER BY start',
        'delete' => 'deleted',
        'hideTable' => true,
        'readOnly' => true,
        'typeicon_classes' => [
            'default' => 'ext-golbevents-occurrence',
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
            'label' => $ll . 'tx_golbevents_domain_model_occurrence.event',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'pages',
                'default' => 0,
            ],
        ],
        'start' => [
            'label' => $ll . 'tx_golbevents_domain_model_occurrence.start',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'end' => [
            'label' => $ll . 'tx_golbevents_domain_model_occurrence.end',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'all_day' => [
            'label' => $ll . 'tx_golbevents_domain_model_occurrence.all_day',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
    ],
    'types' => [
        0 => [
            'showitem' => 'event, start, end, all_day',
        ],
    ],
];
