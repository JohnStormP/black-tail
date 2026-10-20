<?php

namespace App\Analytics\Infrastructure\Console;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Infrastructure\ClickHouse\ClickHouseSchema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'analytics:clickhouse:setup', description: 'Create ClickHouse analytics tables')]
final class SetupClickHouseSchemaCommand extends Command
{
    public function __construct(
        private readonly ClickHouseClientInterface $clickHouse,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->clickHouse->execute(ClickHouseSchema::BET_EVENTS_DDL);
        $io->success('bet_events table done');

        return Command::SUCCESS;
    }
}
