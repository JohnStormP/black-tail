<?php

namespace App\Tests\Player\Infrastructure\Doctrine;

use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePlayerRepositoryTest extends KernelTestCase
{
    private PlayerRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager1 = $entityManager;

        $entityManager1->createQuery('DELETE FROM '.Player::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $playerId = PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $tenantId = TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $player = Player::register(
            $playerId,
            $tenantId,
            PlayerEmail::fromString('player@example.com'),
        );

        $this->repository->save($player);

        $found = $this->repository->findById($playerId);

        $this->assertNotNull($found);
        $this->assertTrue($found->id()->equals($playerId));
        $this->assertTrue($found->tenantId()->equals($tenantId));
        $this->assertSame('player@example.com', $found->email()->toString());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(PlayerNotFoundException::class);

        $this->repository->getById(PlayerId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
