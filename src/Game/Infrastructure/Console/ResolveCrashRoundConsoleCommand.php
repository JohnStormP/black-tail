<?php

namespace App\Game\Infrastructure\Console;

use App\Game\Application\Command\ResolveCrashRoundCommand;
use App\Game\Application\Handler\ResolveCrashRoundHandler;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

#[AsCommand(
    name: 'app:crash:resolve',
    description: 'Crash a running round and settle open bets as lost',
)]
final class ResolveCrashRoundConsoleCommand extends Command
{
    public function __construct(
        private readonly ResolveCrashRoundHandler $resolve,
        private readonly CrashBetRepositoryInterface $bets,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('roundId', InputArgument::REQUIRED, 'Crash round ULID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roundId = (string) $input->getArgument('roundId');
        $openBets = $this->bets->findOpenByRoundId(CrashRoundId::fromString($roundId));

        $settleEntryIdsByBetId = [];
        foreach ($openBets as $bet) {
            $settleEntryIdsByBetId[$bet->id()->toString()] = (string) new Ulid();
        }

        ($this->resolve)(new ResolveCrashRoundCommand($roundId, $settleEntryIdsByBetId));

        new SymfonyStyle($input, $output)->success(sprintf(
            'Resolved crash round "%s" (%d open bet(s) settled as lost).',
            $roundId,
            \count($settleEntryIdsByBetId),
        ));

        return Command::SUCCESS;
    }
}
