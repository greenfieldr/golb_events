<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Tests\Functional\Command;

use Greenfieldr\GolbEvents\Command\GenerateOccurrencesCommand;
use Greenfieldr\GolbEvents\Recurrence\EventSchedule;
use Greenfieldr\GolbEvents\Recurrence\GeneratedOccurrence;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceGenerator;
use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Greenfieldr\GolbEvents\Recurrence\RecurrenceRuleBuilder;
use Greenfieldr\GolbEvents\Tests\Functional\AbstractOccurrenceTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(GenerateOccurrencesCommand::class)]
#[CoversClass(OccurrenceSynchronizer::class)]
#[CoversClass(OccurrenceGenerator::class)]
#[CoversClass(GeneratedOccurrence::class)]
#[CoversClass(EventSchedule::class)]
#[CoversClass(RecurrenceRuleBuilder::class)]
final class GenerateOccurrencesCommandTest extends AbstractOccurrenceTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/EventSite.csv');
        $this->freezeTime();
        $this->tester = new CommandTester($this->get(GenerateOccurrencesCommand::class));
    }

    #[Test]
    public function regeneratesAllEvents(): void
    {
        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Synchronized the occurrences of 3 event(s) up to 2028-01-01.', $this->tester->getDisplay());
        self::assertSame(1 + 32 + 2, $this->countAllOccurrences());
    }

    #[Test]
    public function regeneratesASingleEvent(): void
    {
        $exitCode = $this->tester->execute(['--event' => '5']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Stored 2 occurrence(s) of event 5 up to 2028-01-01.', $this->tester->getDisplay());
        self::assertCount(2, $this->occurrencesOf(5));
        self::assertSame(3 + 2, $this->countAllOccurrences(), 'the fixture occurrences of the other events stay');
    }

    #[Test]
    public function rejectsAnInvalidEventUid(): void
    {
        $exitCode = $this->tester->execute(['--event' => 'abc']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('positive page uid', $this->tester->getDisplay());
        self::assertSame(3, $this->countAllOccurrences(), 'nothing generated');
    }
}
