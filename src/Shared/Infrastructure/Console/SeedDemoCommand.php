<?php

namespace App\Shared\Infrastructure\Console;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Infrastructure\Demo\DemoSeedIds;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\DepositWalletCommand;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\DepositWalletHandler;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:seed',
    description: 'Seed demo tenant, admin user, player and funded wallet',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly RegisterTenantHandler $registerTenant,
        private readonly UserRepositoryInterface $users,
        private readonly RegisterUserHandler $registerUser,
        private readonly PlayerRepositoryInterface $players,
        private readonly RegisterPlayerHandler $registerPlayer,
        private readonly WalletRepositoryInterface $wallets,
        private readonly OpenWalletHandler $openWallet,
        private readonly DepositWalletHandler $depositWallet,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantId = TenantId::fromString(DemoSeedIds::TENANT_ID);
        if (null === $this->tenants->findById($tenantId)) {
            ($this->registerTenant)(new RegisterTenantCommand(
                DemoSeedIds::TENANT_ID,
                DemoSeedIds::TENANT_NAME,
            ));
        }

        $adminId = UserId::fromString(DemoSeedIds::ADMIN_USER_ID);
        if (null === $this->users->findById($adminId)) {
            ($this->registerUser)(new RegisterUserCommand(
                DemoSeedIds::ADMIN_USER_ID,
                DemoSeedIds::ADMIN_EMAIL,
                DemoSeedIds::ADMIN_PASSWORD,
                DemoSeedIds::ADMIN_ROLE,
                DemoSeedIds::TENANT_ID,
            ));
        }

        $playerId = PlayerId::fromString(DemoSeedIds::PLAYER_ID);
        if (null === $this->players->findById($playerId)) {
            ($this->registerPlayer)(new RegisterPlayerCommand(
                DemoSeedIds::PLAYER_ID,
                DemoSeedIds::TENANT_ID,
                DemoSeedIds::PLAYER_EMAIL,
            ));
        }

        $walletId = WalletId::fromString(DemoSeedIds::WALLET_ID);
        if (null === $this->wallets->findById($walletId)) {
            ($this->openWallet)(new OpenWalletCommand(
                DemoSeedIds::WALLET_ID,
                DemoSeedIds::PLAYER_ID,
                DemoSeedIds::WALLET_CURRENCY,
            ));
        }

        ($this->depositWallet)(new DepositWalletCommand(
            DemoSeedIds::WALLET_ID,
            DemoSeedIds::DEPOSIT_LEDGER_ID,
            DemoSeedIds::DEPOSIT_AMOUNT,
        ));

        $io->success('Demo data ready');
        $io->writeln(sprintf('Admin login: %s / %s', DemoSeedIds::ADMIN_EMAIL, DemoSeedIds::ADMIN_PASSWORD));

        return Command::SUCCESS;
    }
}
