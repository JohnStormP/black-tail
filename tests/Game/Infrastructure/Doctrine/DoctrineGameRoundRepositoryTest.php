<?php

namespace App\Tests\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\GameRoundNotFoundException;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineGameRoundRepositoryTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string GAME_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string PLAYER_ID = '01CXYZ00000000000000000001';

    private GameRoundRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(GameRoundRepositoryInterface::class);
        self::assertInstanceOf(GameRoundRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.GameRound::class)->execute();
    }

    public function testSaveAndFindById(): void
    {
        $config = LuckyReelsConfig::default();
        $result = (new LuckyReelsEngine())->evaluateStops($config, [0, 1, 2, 3, 4]);
        $roundId = GameRoundId::fromString(self::ROUND_ID);

        $this->repository->save(GameRound::record(
            $roundId,
            GameId::fromString(self::GAME_ID),
            PlayerId::fromString(self::PLAYER_ID),
            100,
            $config,
            $result,
        ));

        $loaded = $this->repository->findById($roundId);
        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->id()->equals($roundId));
        $this->assertSame(self::GAME_ID, $loaded->gameId()->toString());
        $this->assertSame(self::PLAYER_ID, $loaded->playerId()->toString());
        $this->assertSame(100, $loaded->betMinor());
        $this->assertSame([0, 1, 2, 3, 4], $loaded->stops());
        $this->assertSame($result->totalMultiplier(), $loaded->totalMultiplier());
        $this->assertSame($result->winAmountMinor(100), $loaded->winMinor());
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(GameRoundNotFoundException::class);

        $this->repository->getById(GameRoundId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
