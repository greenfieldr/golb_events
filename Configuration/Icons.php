<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'apps-pagetree-event' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/apps-pagetree-event.svg',
    ],
    'apps-pagetree-event-hideinmenu' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/apps-pagetree-event-hideinmenu.svg',
    ],
    'ext-golbevents-location' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/tx_golbevents_domain_model_location.svg',
    ],
    'ext-golbevents-organizer' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/tx_golbevents_domain_model_organizer.svg',
    ],
    'ext-golbevents-exception' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/tx_golbevents_domain_model_exception.svg',
    ],
    'ext-golbevents-occurrence' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:golb_events/Resources/Public/Icons/tx_golbevents_domain_model_occurrence.svg',
    ],
];
