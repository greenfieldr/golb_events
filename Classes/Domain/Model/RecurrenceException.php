<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A date excluded from the recurrence of an event (table
 * tx_golbevents_domain_model_exception).
 */
class RecurrenceException extends AbstractEntity
{
    protected ?\DateTimeImmutable $date = null;

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }
}
