<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

use Greenfieldr\GolbEvents\Constants;
use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;

return [
    // Golb's Page mapping (table and properties) is inherited by Extbase.
    Event::class => [
        'tableName' => 'pages',
        'recordType' => (string)Constants::EVENT_DOKTYPE,
        'properties' => [
            'start' => ['fieldName' => 'tx_golbevents_start'],
            'end' => ['fieldName' => 'tx_golbevents_end'],
            'allDay' => ['fieldName' => 'tx_golbevents_all_day'],
            'cancelled' => ['fieldName' => 'tx_golbevents_cancelled'],
            'location' => ['fieldName' => 'tx_golbevents_location'],
            'locationText' => ['fieldName' => 'tx_golbevents_location_text'],
            'organizer' => ['fieldName' => 'tx_golbevents_organizer'],
            'organizerText' => ['fieldName' => 'tx_golbevents_organizer_text'],
            'link' => ['fieldName' => 'tx_golbevents_link'],
            'recurrenceFrequency' => ['fieldName' => 'tx_golbevents_recurrence_frequency'],
            'recurrenceInterval' => ['fieldName' => 'tx_golbevents_recurrence_interval'],
            'recurrenceWeekdays' => ['fieldName' => 'tx_golbevents_recurrence_weekdays'],
            'recurrenceMonthlyMode' => ['fieldName' => 'tx_golbevents_recurrence_monthly_mode'],
            'recurrenceEnd' => ['fieldName' => 'tx_golbevents_recurrence_end'],
            'recurrenceUntil' => ['fieldName' => 'tx_golbevents_recurrence_until'],
            'recurrenceCount' => ['fieldName' => 'tx_golbevents_recurrence_count'],
            'exceptions' => ['fieldName' => 'tx_golbevents_exceptions'],
            'occurrences' => ['fieldName' => 'tx_golbevents_occurrences'],
        ],
    ],
    Location::class => [
        'tableName' => 'tx_golbevents_domain_model_location',
    ],
    Organizer::class => [
        'tableName' => 'tx_golbevents_domain_model_organizer',
    ],
    RecurrenceException::class => [
        'tableName' => 'tx_golbevents_domain_model_exception',
    ],
    Occurrence::class => [
        'tableName' => 'tx_golbevents_domain_model_occurrence',
    ],
];
