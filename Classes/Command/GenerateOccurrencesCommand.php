<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "golb_events".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 3
 * of the License, or any later version.
 */

namespace Greenfieldr\GolbEvents\Command;

use Greenfieldr\GolbEvents\Recurrence\OccurrenceSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Regenerates the occurrences of all events (or of one event). Meant to run
 * regularly through the scheduler so the occurrences of recurring events
 * are extended up to the configured horizon.
 */
#[AsCommand(
    name: 'golbevents:occurrences:generate',
    description: 'Regenerates the occurrences of the event pages up to the configured horizon.'
)]
final class GenerateOccurrencesCommand extends Command
{
    public function __construct(private readonly OccurrenceSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('event', 'e', InputOption::VALUE_REQUIRED, 'Regenerate the occurrences of this event page (uid) only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $horizon = $this->synchronizer->getHorizon()->format('Y-m-d');
        $event = $input->getOption('event');

        if (is_string($event) && $event !== '') {
            if (!ctype_digit($event) || (int)$event <= 0) {
                $io->error('The event option must be a positive page uid.');

                return Command::INVALID;
            }
            $count = $this->synchronizer->synchronizeEvent((int)$event);
            $io->success(sprintf('Stored %d occurrence(s) of event %d up to %s.', $count, (int)$event, $horizon));

            return Command::SUCCESS;
        }

        $count = $this->synchronizer->synchronizeAll();
        $io->success(sprintf('Regenerated the occurrences of %d event(s) up to %s.', $count, $horizon));

        return Command::SUCCESS;
    }
}
