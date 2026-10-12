<?php

namespace App\Tests\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\Exception\TenantGameNotFoundException;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTenantGameRepositoryTest extends KernelTestCase
{
    private const string TENANT_GAME_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';

    private TenantGameRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(TenantGameRepositoryInterface::class);
        self::assertInstanceOf(TenantGameRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.TenantGame::class)->execute();
    }

    public function testSaveFindByIdAndFindByTenantAndGame(): void
    {
        $id = TenantGameId::fromString(self::TENANT_GAME_ID);
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $gameId = GameId::fromString(self::GAME_ID);

        $this->repository->save(TenantGame::assign($id, $tenantId, $gameId));

        $byId = $this->repository->findById($id);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($id));
        $this->assertTrue($byId->tenantId()->equals($tenantId));
        $this->assertTrue($byId->gameId()->equals($gameId));
        $this->assertTrue($byId->isEnabled());

        $byPair = $this->repository->findByTenantAndGame($tenantId, $gameId);
        $this->assertNotNull($byPair);
        $this->assertTrue($byPair->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(TenantGameNotFoundException::class);

        $this->repository->getById(TenantGameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testFindByTenantAndGameReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findByTenantAndGame(
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        ));
    }
}
