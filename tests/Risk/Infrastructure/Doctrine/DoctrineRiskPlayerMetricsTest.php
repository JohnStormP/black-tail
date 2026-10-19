<?php

namespace App\Tests\Risk\Infrastructure\Doctrine;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentIdempotencyKey;
use App\Payment\Domain\ValueObject\PaymentType;
use App\Player\Domain\ValueObject\PlayerId;
use App\Risk\Domain\Service\RiskPlayerMetricsInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRiskPlayerMetricsTest extends KernelTestCase
{
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string OTHER_PLAYER_ID = '01CXYZ00000000000000000001';

    private RiskPlayerMetricsInterface $metrics;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $metrics = self::getContainer()->get(RiskPlayerMetricsInterface::class);
        self::assertInstanceOf(RiskPlayerMetricsInterface::class, $metrics);
        $this->metrics = $metrics;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $entityManager->createQuery('DELETE FROM '.Bet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Payment::class)->execute();
    }

    public function testSumDepositMinorSinceCountsInFlightAndCompleted(): void
    {
        $this->persistDeposit('01ARZ3NDEKTSV4RRFFQ69G5FAV', self::PLAYER_ID, 1000, new \DateTimeImmutable('2026-10-19 08:00:00'));
        $this->persistDeposit('01ARZ3NDEKTSV4RRFFQ69G5FB0', self::PLAYER_ID, 500, new \DateTimeImmutable('2026-10-19 09:00:00'));
        $this->persistDeposit('01ARZ3NDEKTSV4RRFFQ69G5FB1', self::OTHER_PLAYER_ID, 9999, new \DateTimeImmutable('2026-10-19 09:00:00'));
        $this->persistDeposit('01ARZ3NDEKTSV4RRFFQ69G5FB2', self::PLAYER_ID, 200, new \DateTimeImmutable('2026-10-18 09:00:00'));

        $sum = $this->metrics->sumDepositMinorSince(
            PlayerId::fromString(self::PLAYER_ID),
            new \DateTimeImmutable('2026-10-19 00:00:00'),
        );

        $this->assertSame(1500, $sum);
    }

    public function testSumAndCountBetsExcludeCancelled(): void
    {
        $kept = Bet::place(
            BetId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            PlayerId::fromString(self::PLAYER_ID),
            GameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR0'),
            GameRoundId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR1'),
            Money::of(300, Currency::Usd),
            BetIdempotencyKey::fromString('bet-1'),
            createdAt: new \DateTimeImmutable('2026-10-19 10:00:00'),
        );
        $cancelled = Bet::place(
            BetId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            PlayerId::fromString(self::PLAYER_ID),
            GameId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR0'),
            GameRoundId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR2'),
            Money::of(700, Currency::Usd),
            BetIdempotencyKey::fromString('bet-2'),
            createdAt: new \DateTimeImmutable('2026-10-19 10:01:00'),
        );
        $cancelled->cancel();

        $this->entityManager->persist($kept);
        $this->entityManager->persist($cancelled);
        $this->entityManager->flush();

        $since = new \DateTimeImmutable('2026-10-19 00:00:00');
        $playerId = PlayerId::fromString(self::PLAYER_ID);

        $this->assertSame(300, $this->metrics->sumBettingMinorSince($playerId, $since));
        $this->assertSame(1, $this->metrics->countBetsSince($playerId, $since));
    }

    private function persistDeposit(
        string $id,
        string $playerId,
        int $amount,
        \DateTimeImmutable $createdAt,
    ): void {
        $payment = Payment::initiate(
            PaymentId::fromString($id),
            TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR3'),
            PlayerId::fromString($playerId),
            WalletId::fromString('01BX5ZZKBKACTAV9WEVGEMMVR4'),
            PaymentType::Deposit,
            Money::of($amount, Currency::Usd),
            PaymentIdempotencyKey::fromString('dep-'.$id),
            'fake',
            createdAt: $createdAt,
        );
        $payment->markProcessing('ref-'.$id);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }
}
