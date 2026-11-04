<?php

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Golb Events',
    'description' => 'Golb Events - Events The Page Way! An add-on to the TYPO3 blog extension Golb that uses regular pages as events.',
    'category' => 'fe',
    'author' => 'Marcel Küpper',
    'author_email' => 'typo3dev@kuepper.net',
    'state' => 'stable',
    'version' => '14.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.2.0-8.5.99',
            'golb' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
