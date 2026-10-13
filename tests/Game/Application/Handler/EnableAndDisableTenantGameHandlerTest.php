<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\AssignGameToTenantCommand;
use App\Game\Application\Command\DisableTenantGameCommand;
use App\Game\Application\Command\EnableTenantGameCommand;
use App\Game\Application\Handler\AssignGameToTenantHandler;
use App\Game\Application\Handler\DisableTenantGameHandler;
use App\Game\Application\Handler\EnableTenantGameHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\InvalidTenantGameIdException;
use App\Game\Domain\Exception\TenantGameNotFoundException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EnableAndDisableTenantGameHandlerTest extends KernelTestCase
{
    private const string TENANT_GAME_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';

    private AssignGameToTenantHandler $assign;
    private EnableTenantGameHandler $enable;
    private DisableTenantGameHandler $disable;
    private TenantGameRepositoryInterface $tenantGames;

    protected function setUp(): void
    {
        self::bootKernel();

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $games = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);

        $tenantGames = self::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $tenantGames);
        $this->tenantGames = $tenantGames;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(
            TenantId::fromString(self::TENANT_ID),
            'Casino Alpha',
        ));
        $games->save(Game::register(
            GameId::fromString(self::GAME_ID),
            GameCode::fromString('lucky_reels'),
            'Lucky Reels',
        ));

        $this->assign = new AssignGameToTenantHandler($tenants, $games, $this->tenantGames);
        $this->enable = new EnableTenantGameHandler($this->tenantGames);
        $this->disable = new DisableTenantGameHandler($this->tenantGames);
    }

    public function testDisableThenEnable(): void
    {
        ($this->assign)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));

        ($this->disable)(new DisableTenantGameCommand(self::TENANT_GAME_ID));
        $disabled = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertFalse($disabled->isEnabled());

        ($this->enable)(new EnableTenantGameCommand(self::TENANT_GAME_ID));
        $enabled = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertTrue($enabled->isEnabled());
    }

    public function testDisableIsIdempotent(): void
    {
        ($this->assign)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));

        ($this->disable)(new DisableTenantGameCommand(self::TENANT_GAME_ID));
        ($this->disable)(new DisableTenantGameCommand(self::TENANT_GAME_ID));

        $link = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertFalse($link->isEnabled());
    }

    public function testEnableIsIdempotent(): void
    {
        ($this->assign)(new AssignGameToTenantCommand(
            self::TENANT_GAME_ID,
            self::TENANT_ID,
            self::GAME_ID,
        ));

        ($this->enable)(new EnableTenantGameCommand(self::TENANT_GAME_ID));
        ($this->enable)(new EnableTenantGameCommand(self::TENANT_GAME_ID));

        $link = $this->tenantGames->getById(TenantGameId::fromString(self::TENANT_GAME_ID));
        $this->assertTrue($link->isEnabled());
    }

    public function testDisableUnknownFails(): void
    {
        $this->expectException(TenantGameNotFoundException::class);

        ($this->disable)(new DisableTenantGameCommand('01DXYZ00000000000000000002'));
    }

    public function testEnableUnknownFails(): void
    {
        $this->expectException(TenantGameNotFoundException::class);

        ($this->enable)(new EnableTenantGameCommand('01DXYZ00000000000000000002'));
    }

    public function testRejectsInvalidId(): void
    {
        $this->expectException(InvalidTenantGameIdException::class);

        ($this->enable)(new EnableTenantGameCommand('not-a-ulid'));
    }
}
