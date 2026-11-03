<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Domain\Model;

use Greenfieldr\GolbEvents\Domain\Model\Event;
use Greenfieldr\GolbEvents\Domain\Model\Location;
use Greenfieldr\GolbEvents\Domain\Model\Occurrence;
use Greenfieldr\GolbEvents\Domain\Model\Organizer;
use Greenfieldr\GolbEvents\Domain\Model\RecurrenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Round-trip test for the plain accessors of the models, which guards
 * against mismatched getter and setter pairs.
 */
#[CoversClass(Event::class)]
#[CoversClass(Location::class)]
#[CoversClass(Organizer::class)]
#[CoversClass(RecurrenceException::class)]
#[CoversClass(Occurrence::class)]
final class AccessorsTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractEntity>, string, mixed}>
     */
    public static function accessorDataProvider(): iterable
    {
        $date = new \DateTimeImmutable('2026-05-04 19:30:00');
        $models = [
            Event::class => [
                'start' => $date, 'end' => $date, 'allDay' => true, 'cancelled' => true,
                'location' => new Location(), 'locationText' => 'Town hall',
                'organizer' => new Organizer(), 'organizerText' => 'Volunteers', 'link' => 't3://page?uid=1',
                'recurrenceFrequency' => Event::FREQUENCY_WEEKLY, 'recurrenceInterval' => 2,
                'recurrenceWeekdays' => 'MO,WE', 'recurrenceMonthlyMode' => Event::MONTHLY_MODE_WEEKDAY,
                'recurrenceEnd' => Event::RECURRENCE_END_COUNT, 'recurrenceUntil' => $date, 'recurrenceCount' => 5,
            ],
            Location::class => [
                'name' => 'Hall', 'street' => 'Main street 1', 'zip' => '12345', 'city' => 'Cologne', 'country' => 'DE',
                'latitude' => 50.9, 'longitude' => 6.9, 'timezone' => 'Europe/Berlin', 'link' => 'https://example.org',
                'description' => 'Description',
            ],
            Organizer::class => [
                'name' => 'Society', 'email' => 'mail@example.org', 'phone' => '+49 1', 'link' => 'https://example.org',
                'description' => 'Description',
            ],
            RecurrenceException::class => [
                'date' => $date,
            ],
            Occurrence::class => [
                'event' => new Event(), 'start' => $date, 'end' => $date, 'allDay' => true,
            ],
        ];
        foreach ($models as $className => $properties) {
            foreach ($properties as $property => $value) {
                yield substr($className, (int)strrpos($className, '\\') + 1) . '::' . $property => [$className, $property, $value];
            }
        }
    }

    /**
     * @param class-string<AbstractEntity> $className
     */
    #[Test]
    #[DataProvider('accessorDataProvider')]
    public function valueSetBySetterIsReturnedByGetter(string $className, string $property, mixed $value): void
    {
        $subject = new $className();
        $setter = 'set' . ucfirst($property);
        $getter = (is_bool($value) ? 'is' : 'get') . ucfirst($property);

        self::assertTrue(method_exists($subject, $setter), 'Missing setter ' . $setter);
        self::assertTrue(method_exists($subject, $getter), 'Missing getter ' . $getter);

        (new \ReflectionMethod($subject, $setter))->invoke($subject, $value);

        self::assertSame($value, (new \ReflectionMethod($subject, $getter))->invoke($subject));
    }

    #[Test]
    public function nullableValuesCanBeReset(): void
    {
        $event = new Event();
        $event->setStart(new \DateTimeImmutable());
        $event->setStart(null);
        $event->setLocation(new Location());
        $event->setLocation(null);

        $location = new Location();
        $location->setLatitude(1.0);
        $location->setLatitude(null);

        self::assertNull($event->getStart());
        self::assertNull($event->getLocation());
        self::assertNull($location->getLatitude());
        self::assertFalse($location->hasCoordinates());
    }
}
