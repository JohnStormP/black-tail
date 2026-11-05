<?php

namespace App\Shared\Infrastructure\Console;

use App\Game\Application\Command\AssignGameToTenantCommand;
use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\AssignGameToTenantHandler;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
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
    description: 'Seed demo tenant, users, games, player and funded wallet',
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
        private readonly GameRepositoryInterface $games,
        private readonly RegisterGameHandler $registerGame,
        private readonly AssignGameToTenantHandler $assignGameToTenant,
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

        $superAdminId = UserId::fromString(DemoSeedIds::SUPER_ADMIN_USER_ID);
        if (null === $this->users->findById($superAdminId)) {
            ($this->registerUser)(new RegisterUserCommand(
                DemoSeedIds::SUPER_ADMIN_USER_ID,
                DemoSeedIds::SUPER_ADMIN_EMAIL,
                DemoSeedIds::SUPER_ADMIN_PASSWORD,
                DemoSeedIds::SUPER_ADMIN_ROLE,
                null,
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

        $this->seedGame(
            DemoSeedIds::LUCKY_REELS_GAME_ID,
            DemoSeedIds::LUCKY_REELS_CODE,
            DemoSeedIds::LUCKY_REELS_NAME,
            DemoSeedIds::LUCKY_REELS_TENANT_GAME_ID,
        );
        $this->seedGame(
            DemoSeedIds::CRASH_GAME_ID,
            DemoSeedIds::CRASH_CODE,
            DemoSeedIds::CRASH_NAME,
            DemoSeedIds::CRASH_TENANT_GAME_ID,
        );

        $io->success('Demo data ready');
        $io->writeln(sprintf('Admin login: %s / %s', DemoSeedIds::ADMIN_EMAIL, DemoSeedIds::ADMIN_PASSWORD));
        $io->writeln(sprintf('Super login: %s / %s', DemoSeedIds::SUPER_ADMIN_EMAIL, DemoSeedIds::SUPER_ADMIN_PASSWORD));

        return Command::SUCCESS;
    }

    private function seedGame(string $gameId, string $code, string $name, string $tenantGameId): void
    {
        $gameCode = GameCode::fromString($code);
        if (null === $this->games->findByCode($gameCode)) {
            ($this->registerGame)(new RegisterGameCommand($gameId, $code, $name));
        }

        ($this->assignGameToTenant)(new AssignGameToTenantCommand(
            $tenantGameId,
            DemoSeedIds::TENANT_ID,
            $gameId,
        ));
    }
}
