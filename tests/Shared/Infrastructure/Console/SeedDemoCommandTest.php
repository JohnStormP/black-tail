<?php

namespace App\Tests\Shared\Infrastructure\Console;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserId;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Infrastructure\Demo\DemoSeedIds;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoCommandTest extends WebTestCase
{
    public function testSeedIsIdempotentAndAdminCanLogin(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:demo:seed'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        self::assertNotNull($tenants->findById(TenantId::fromString(DemoSeedIds::TENANT_ID)));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        self::assertNotNull($users->findById(UserId::fromString(DemoSeedIds::ADMIN_USER_ID)));
        self::assertNotNull($users->findById(UserId::fromString(DemoSeedIds::SUPER_ADMIN_USER_ID)));

        $players = static::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        self::assertNotNull($players->findById(PlayerId::fromString(DemoSeedIds::PLAYER_ID)));

        $wallets = static::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $wallet = $wallets->getById(WalletId::fromString(DemoSeedIds::WALLET_ID));
        self::assertSame(DemoSeedIds::DEPOSIT_AMOUNT, $wallet->availableBalance()->amount());

        $games = static::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        self::assertNotNull($games->findByCode(GameCode::fromString(DemoSeedIds::LUCKY_REELS_CODE)));
        self::assertNotNull($games->findByCode(GameCode::fromString(DemoSeedIds::CRASH_CODE)));

        $tenantGames = static::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $tenantId = TenantId::fromString(DemoSeedIds::TENANT_ID);
        $luckyTenantGame = $tenantGames->findByTenantAndGame(
            $tenantId,
            GameId::fromString(DemoSeedIds::LUCKY_REELS_GAME_ID),
        );
        self::assertNotNull($luckyTenantGame);
        self::assertTrue($luckyTenantGame->isEnabled());
        $crashTenantGame = $tenantGames->findByTenantAndGame(
            $tenantId,
            GameId::fromString(DemoSeedIds::CRASH_GAME_ID),
        );
        self::assertNotNull($crashTenantGame);
        self::assertTrue($crashTenantGame->isEnabled());

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => DemoSeedIds::ADMIN_EMAIL,
                'password' => DemoSeedIds::ADMIN_PASSWORD,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(200);

        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => DemoSeedIds::SUPER_ADMIN_EMAIL,
                'password' => DemoSeedIds::SUPER_ADMIN_PASSWORD,
            ], \JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(200);
    }
}
