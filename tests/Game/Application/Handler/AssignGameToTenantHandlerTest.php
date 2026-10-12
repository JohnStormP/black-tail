<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\AssignGameToTenantCommand;
use App\Game\Application\Handler\AssignGameToTenantHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\Exception\InvalidTenantGameIdException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssignGameToTenantHandlerTest extends KernelTestCase
{
    private const string TENANT_GAME_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';

    private AssignGameToTenantHandler $handler;
    private TenantRepositoryInterface $tenants;
    private GameRepositoryInterface $games;
    private TenantGameRepositoryInterface $tenantGames;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $this->tenants = $tenants;

        $games = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $this->games = $games;

        $tenantGames = self::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $this->tenantGames = $tenantGames;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $this->entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Game::class)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $this->handler = new AssignGameToTenantHandler(
            $this->tenants,
            $this->games,
            $this->tenantGames,
        );
    }

    public function testAssignsEnabledTenantGame(): void
    {
        $this->seedTenantAndGame();

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));

        $link = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertTrue($link->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertTrue($link->gameId()->equals(GameId::fromString(self::GAME_ID)));
        $this->assertTrue($link->isEnabled());
    }

    public function testAssignIsIdempotentForSameTenantAndGame(): void
    {
        $this->seedTenantAndGame();

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));

        // Second call with a different TenantGame id must not throw and must not
        // create a second row (unique tenant_id+game_id).
        ($this->handler)(new AssignGameToTenantCommand(
            '01DXYZ00000000000000000002',
            self::TENANT_ID,
            self::GAME_ID,
        ));

        $existing = $this->tenantGames->findByTenantAndGame(
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        );
        $this->assertNotNull($existing);
        $this->assertTrue($existing->id()->equals(TenantGameId::fromString(self::TENANT_GAME_ID)));

        $count = (int) $this->entityManager->createQuery(
            'SELECT COUNT(tg.id) FROM '.TenantGame::class.' tg'
        )->getSingleScalarResult();
        $this->assertSame(1, $count);
    }

    public function testAssignDoesNotReEnableDisabledLink(): void
    {
        $this->seedTenantAndGame();

        $link = TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        );
        $link->disable();
        $this->tenantGames->save($link);
        $this->entityManager->clear();

        ($this->handler)(new AssignGameToTenantCommand(
            '01DXYZ00000000000000000002',
            self::TENANT_ID,
            self::GAME_ID,
        ));

        $again = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertFalse($again->isEnabled());
    }

    public function testRejectsUnknownTenant(): void
    {
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));

        $this->expectException(TenantNotFoundException::class);

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));
    }

    public function testRejectsUnknownGame(): void
    {
        $this->tenants->save(Tenant::register(
            TenantId::fromString(self::TENANT_ID),
            'Casino Alpha',
        ));

        $this->expectException(GameNotFoundException::class);

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));
    }

    public function testRejectsInvalidTenantGameId(): void
    {
        $this->expectException(InvalidTenantGameIdException::class);

        ($this->handler)(new AssignGameToTenantCommand(
            'not-a-ulid',
            self::TENANT_ID,
            self::GAME_ID,
        ));
    }

    public function testRejectsInvalidTenantId(): void
    {
        $this->expectException(InvalidTenantIdException::class);

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            'not-a-ulid',
            self::GAME_ID,
        ));
    }

    public function testRejectsInvalidGameId(): void
    {
        $this->expectException(InvalidGameIdException::class);

        ($this->handler)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            'not-a-ulid',
        ));
    }

    private function seedTenantAndGame(): void
    {
        $this->tenants->save(Tenant::register(
            TenantId::fromString(self::TENANT_ID),
            'Casino Alpha',
        ));
        $this->games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));
    }
}
