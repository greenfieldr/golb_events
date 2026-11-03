<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Property\TypeConverter;

use Greenfieldr\GolbEvents\Property\TypeConverter\DateTimeImmutableConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(DateTimeImmutableConverter::class)]
final class DateTimeImmutableConverterTest extends UnitTestCase
{
    private DateTimeImmutableConverter $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new DateTimeImmutableConverter(new DateTimeConverter());
    }

    private function configuration(string $format): PropertyMappingConfiguration
    {
        $configuration = new PropertyMappingConfiguration();
        $configuration->setTypeConverterOption(DateTimeConverter::class, DateTimeConverter::CONFIGURATION_DATE_FORMAT, $format);

        return $configuration;
    }

    #[Test]
    public function convertsAStringInTheConfiguredFormat(): void
    {
        $date = $this->subject->convertFrom('2026-06-01', \DateTimeImmutable::class, [], $this->configuration('Y-m-d'));

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-06-01', $date->format('Y-m-d'));
    }

    #[Test]
    public function convertsADateWithTimezoneInTheDefaultFormat(): void
    {
        $date = $this->subject->convertFrom('2026-06-01T10:00:00+02:00', \DateTimeImmutable::class, [], new PropertyMappingConfiguration());

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-06-01T10:00:00+02:00', $date->format('c'));
    }

    #[Test]
    public function emptyStringBecomesNull(): void
    {
        self::assertNull($this->subject->convertFrom('', \DateTimeImmutable::class, [], $this->configuration('Y-m-d')));
    }
}
