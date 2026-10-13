<?php

namespace App\Tests\Betting\Infrastructure\Doctrine;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\BetNotFoundException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineBetRepositoryTest extends KernelTestCase
{
    private const string BET_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';
    private const string ROUND_ID = '01DXYZ00000000000000000002';
    private const string IDEMPOTENCY_KEY = 'spin-req-001';

    private BetRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(BetRepositoryInterface::class);
        self::assertInstanceOf(BetRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Bet::class)->execute();
    }

    public function testSaveFindByIdAndFindByIdempotencyKey(): void
    {
        $id = BetId::fromString(self::BET_ID);
        $key = BetIdempotencyKey::fromString(self::IDEMPOTENCY_KEY);

        $this->repository->save($this->newBet($id, $key));

        $byId = $this->repository->findById($id);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($id));
        $this->assertTrue($byId->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertSame(100, $byId->amount()->amount());
        $this->assertSame(Currency::Usd, $byId->amount()->currency());
        $this->assertSame(BetStatus::Pending, $byId->status());
        $this->assertSame('prov-1', $byId->providerReference());

        $byKey = $this->repository->findByIdempotencyKey($key);
        $this->assertNotNull($byKey);
        $this->assertTrue($byKey->id()->equals($id));

        $loaded = $this->repository->getById($id);
        $this->assertTrue($loaded->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(BetNotFoundException::class);

        $this->repository->getById(BetId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testFindByIdempotencyKeyReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findByIdempotencyKey(
            BetIdempotencyKey::fromString('missing-key'),
        ));
    }

    private function newBet(BetId $id, BetIdempotencyKey $key): Bet
    {
        return Bet::place(
            $id,
            PlayerId::fromString(self::PLAYER_ID),
            GameId::fromString(self::GAME_ID),
            GameRoundId::fromString(self::ROUND_ID),
            Money::of(100, Currency::Usd),
            $key,
            'prov-1',
        );
    }
}
