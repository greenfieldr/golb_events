<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Property\TypeConverter;

use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Maps request arguments onto \DateTimeImmutable properties (e.g. the date
 * range of the events demand). Extbase ships a converter for \DateTime
 * only; this one delegates to it, so the same date format configuration
 * (DateTimeConverter::CONFIGURATION_DATE_FORMAT) applies.
 */
final class DateTimeImmutableConverter extends AbstractTypeConverter
{
    public function __construct(private readonly DateTimeConverter $dateTimeConverter) {}

    /**
     * @param mixed $source
     * @param array<mixed> $convertedChildProperties
     */
    public function convertFrom(
        $source,
        string $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ): \DateTimeImmutable|Error|null {
        if (!is_string($source) && !is_int($source) && !is_array($source)) {
            return new Error('Only strings, integers and arrays can be converted to a date.', 1762171200);
        }
        $date = $this->dateTimeConverter->convertFrom($source, \DateTime::class, $convertedChildProperties, $configuration);

        return $date instanceof \DateTime ? \DateTimeImmutable::createFromMutable($date) : $date;
    }
}
