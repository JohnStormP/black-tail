<?php

namespace App\Tests\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Exception\GameNotFoundException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineGameRepositoryTest extends KernelTestCase
{
    private GameRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();
    }

    public function testSaveFindByIdAndFindByCode(): void
    {
        $gameId = GameId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $code = GameCode::fromString('lucky_reels');

        $this->repository->save(Game::register($gameId, $code, 'Lucky Reels'));

        $byId = $this->repository->findById($gameId);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($gameId));
        $this->assertSame('lucky_reels', $byId->code()->toString());
        $this->assertSame('Lucky Reels', $byId->name());

        $byCode = $this->repository->findByCode($code);
        $this->assertNotNull($byCode);
        $this->assertTrue($byCode->id()->equals($gameId));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(GameNotFoundException::class);

        $this->repository->getById(GameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
