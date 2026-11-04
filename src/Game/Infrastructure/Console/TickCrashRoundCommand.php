<?php

namespace App\Game\Infrastructure\Console;

use App\Game\Application\Command\PublishCrashFlightCommand;
use App\Game\Application\Handler\PublishCrashFlightHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:crash:tick',
    description: 'Publish Mercure flight ticks for a running crash round',
)]
final class TickCrashRoundCommand extends Command
{
    public function __construct(
        private readonly PublishCrashFlightHandler $publishFlight,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('roundId', InputArgument::REQUIRED, 'Crash round ULID')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Tick step in hundredths', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roundId = (string) $input->getArgument('roundId');
        $step = max(1, (int) $input->getOption('step'));

        ($this->publishFlight)(new PublishCrashFlightCommand($roundId, $step));

        new SymfonyStyle($input, $output)->success(sprintf(
            'Published crash flight ticks for round "%s" (step %d).',
            $roundId,
            $step,
        ));

        return Command::SUCCESS;
    }
}
