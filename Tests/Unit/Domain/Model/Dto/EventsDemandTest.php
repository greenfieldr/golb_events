<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Unit\Domain\Model\Dto;

use Greenfieldr\Golb\Domain\Model\Dto\PostsDemand;
use Greenfieldr\GolbEvents\Domain\Model\Dto\EventsDemand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(EventsDemand::class)]
final class EventsDemandTest extends UnitTestCase
{
    #[Test]
    public function defaultsApplyWhenNothingIsSet(): void
    {
        $demand = new EventsDemand();

        self::assertFalse($demand->hasMode());
        self::assertSame(EventsDemand::MODE_UPCOMING, $demand->getMode());
        self::assertFalse($demand->hasFrom());
        self::assertNull($demand->getFrom());
        self::assertFalse($demand->hasTo());
        self::assertNull($demand->getTo());
        self::assertFalse($demand->hasOrder());
        self::assertSame(EventsDemand::ORDER_START, $demand->getOrder());
        self::assertFalse($demand->hasOrderDirection());
        self::assertSame('ASC', $demand->getOrderDirection());
        self::assertFalse($demand->hasCategories());
        self::assertFalse($demand->hasTags());
        self::assertSame(1, $demand->getCurrentPage());
    }

    #[Test]
    public function modeAcceptsKnownValuesOnly(): void
    {
        $demand = new EventsDemand();

        foreach ([EventsDemand::MODE_UPCOMING, EventsDemand::MODE_PAST, EventsDemand::MODE_ALL, EventsDemand::MODE_RANGE] as $mode) {
            $demand->setMode($mode);
            self::assertTrue($demand->hasMode());
            self::assertSame($mode, $demand->getMode());
        }

        $demand->setMode('bogus');
        self::assertFalse($demand->hasMode());
        self::assertSame(EventsDemand::MODE_DEFAULT, $demand->getMode());
    }

    #[Test]
    public function pastOccurrencesAreSortedDescendingUnlessSetExplicitly(): void
    {
        $demand = (new EventsDemand())->setMode(EventsDemand::MODE_PAST);
        self::assertSame('DESC', $demand->getOrderDirection());

        $demand->setOrderDirection('asc');
        self::assertSame('ASC', $demand->getOrderDirection());

        self::assertSame('ASC', (new EventsDemand())->setMode(EventsDemand::MODE_ALL)->getOrderDirection());
        self::assertSame('ASC', (new EventsDemand())->setMode(EventsDemand::MODE_RANGE)->getOrderDirection());
    }

    #[Test]
    public function orderFromGolbIsKept(): void
    {
        $demand = (new EventsDemand())->setOrder(PostsDemand::ORDER_TITLE);

        self::assertTrue($demand->hasOrder());
        self::assertSame(PostsDemand::ORDER_TITLE, $demand->getOrder());
    }

    #[Test]
    public function fromAndToCanBeSetAndReset(): void
    {
        $from = new \DateTimeImmutable('2026-06-01');
        $to = new \DateTimeImmutable('2026-06-30');
        $demand = (new EventsDemand())->setFrom($from)->setTo($to);

        self::assertTrue($demand->hasFrom());
        self::assertSame($from, $demand->getFrom());
        self::assertTrue($demand->hasTo());
        self::assertSame($to, $demand->getTo());

        $demand->setFrom(null)->setTo(null);
        self::assertFalse($demand->hasFrom());
        self::assertFalse($demand->hasTo());
    }
}
