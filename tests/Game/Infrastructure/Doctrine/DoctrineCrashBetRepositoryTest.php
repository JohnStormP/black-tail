<?php

namespace App\Tests\Game\Infrastructure\Doctrine;

use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Exception\CrashBetNotFoundException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCrashBetRepositoryTest extends KernelTestCase
{
    private CrashBetRepositoryInterface $bets;
    private CrashRoundRepositoryInterface $rounds;
    private TransactionManagerInterface $transactions;

    protected function setUp(): void
    {
        self::bootKernel();

        $bets = self::getContainer()->get(CrashBetRepositoryInterface::class);
        self::assertInstanceOf(CrashBetRepositoryInterface::class, $bets);
        $this->bets = $bets;

        $rounds = self::getContainer()->get(CrashRoundRepositoryInterface::class);
        self::assertInstanceOf(CrashRoundRepositoryInterface::class, $rounds);
        $this->rounds = $rounds;

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);
        $this->transactions = $transactions;

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.CrashBet::class)->execute();
        $em->createQuery('DELETE FROM '.CrashRound::class)->execute();
    }

    public function testGetByIdForUpdateInsideTransaction(): void
    {
        $roundId = CrashRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $betId = CrashBetId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $crashPoint = (new CrashEngine())->rollCrashPoint(new SeededRandomIntGenerator(42));
        $this->rounds->save(CrashRound::open($roundId, $crashPoint));
        $this->bets->save(CrashBet::place(
            $betId,
            $roundId,
            PlayerId::fromString('01CXYZ00000000000000000001'),
            WalletId::fromString('01EXYZ00000000000000000003'),
            Money::of(500, Currency::Usd),
        ));

        $locked = $this->transactions->transactional(
            fn (): CrashBet => $this->bets->getByIdForUpdate($betId),
        );

        $this->assertTrue($locked->id()->equals($betId));
    }

    public function testGetByIdForUpdateThrowsWhenMissing(): void
    {
        $this->expectException(CrashBetNotFoundException::class);

        $this->transactions->transactional(function (): void {
            $this->bets->getByIdForUpdate(CrashBetId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
        });
    }

    public function testFindOpenByRoundIdForUpdateReturnsOpenBets(): void
    {
        $roundId = CrashRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $betId = CrashBetId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $crashPoint = (new CrashEngine())->rollCrashPoint(new SeededRandomIntGenerator(42));
        $this->rounds->save(CrashRound::open($roundId, $crashPoint));
        $this->bets->save(CrashBet::place(
            $betId,
            $roundId,
            PlayerId::fromString('01CXYZ00000000000000000001'),
            WalletId::fromString('01EXYZ00000000000000000003'),
            Money::of(500, Currency::Usd),
        ));

        $open = $this->transactions->transactional(
            fn (): array => $this->bets->findOpenByRoundIdForUpdate($roundId),
        );

        $this->assertCount(1, $open);
        $this->assertTrue($open[0]->id()->equals($betId));
    }
}
