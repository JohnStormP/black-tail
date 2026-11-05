<?php

namespace App\Tests\Bonus\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetAccepted;
use App\Betting\Domain\ValueObject\BetId;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Bonus\Infrastructure\EventListener\ApplyWageringOnBetAcceptedListener;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ApplyWageringOnBetAcceptedListenerTest extends KernelTestCase
{
    private const string BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string BET_ID = '01EXYZ00000000000000000003';
    private const string WALLET_ID = '01FXYZ00000000000000000004';

    private PlayerBonusRepositoryInterface $playerBonuses;
    private ApplyWageringOnBetAcceptedListener $listener;

    /** @var list<PlayerBonusCompleted> */
    private array $recordedEvents = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $playerBonuses = self::getContainer()->get(PlayerBonusRepositoryInterface::class);
        self::assertInstanceOf(PlayerBonusRepositoryInterface::class, $playerBonuses);
        $this->playerBonuses = $playerBonuses;

        $this->recordedEvents = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PlayerBonusCompleted::class, function (PlayerBonusCompleted $event): void {
            $this->recordedEvents[] = $event;
        });
        $wallets = self::getContainer()->get(\App\Wallet\Domain\Repository\WalletRepositoryInterface::class);
        self::assertInstanceOf(\App\Wallet\Domain\Repository\WalletRepositoryInterface::class, $wallets);
        $ledger = self::getContainer()->get(\App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(\App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface::class, $ledger);
        $transactions = self::getContainer()->get(\App\Shared\Application\TransactionManagerInterface::class);
        self::assertInstanceOf(\App\Shared\Application\TransactionManagerInterface::class, $transactions);
        $this->listener = new ApplyWageringOnBetAcceptedListener(
            $this->playerBonuses,
            $wallets,
            $ledger,
            $transactions,
            $dispatcher,
        );

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
    }

    public function testAppliesWageringToOldestActiveBonus(): void
    {
        $this->playerBonuses->save(PlayerBonus::grant(
            PlayerBonusId::fromString(self::BONUS_ID),
            PlayerId::fromString(self::PLAYER_ID),
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
            TenantId::fromString(self::TENANT_ID),
            Money::of(1000, Currency::Usd),
            Money::of(5000, Currency::Usd),
        ));

        ($this->listener)(new BetAccepted(
            BetId::fromString(self::BET_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            Money::of(2000, Currency::Usd),
        ));

        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::BONUS_ID));
        $this->assertSame(2000, $bonus->wageringProgress()->amount());
        $this->assertSame(PlayerBonusStatus::Active, $bonus->status());
        $this->assertCount(0, $this->recordedEvents);
    }

    public function testDoesNothingWhenNoActiveBonus(): void
    {
        ($this->listener)(new BetAccepted(
            BetId::fromString(self::BET_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            Money::of(2000, Currency::Usd),
        ));

        $this->assertNull($this->playerBonuses->findOldestActiveByPlayer(
            PlayerId::fromString(self::PLAYER_ID),
            Currency::Usd,
        ));
        $this->assertCount(0, $this->recordedEvents);
    }

    public function testCompletesBonusWhenRequirementMet(): void
    {
        $this->playerBonuses->save(PlayerBonus::grant(
            PlayerBonusId::fromString(self::BONUS_ID),
            PlayerId::fromString(self::PLAYER_ID),
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
            TenantId::fromString(self::TENANT_ID),
            Money::of(1000, Currency::Usd),
            Money::of(2000, Currency::Usd),
        ));

        ($this->listener)(new BetAccepted(
            BetId::fromString(self::BET_ID),
            PlayerId::fromString(self::PLAYER_ID),
            WalletId::fromString(self::WALLET_ID),
            Money::of(2000, Currency::Usd),
        ));

        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::BONUS_ID));
        $this->assertSame(PlayerBonusStatus::Completed, $bonus->status());
        $this->assertSame(2000, $bonus->wageringProgress()->amount());
        $this->assertCount(1, $this->recordedEvents);
        $this->assertTrue($this->recordedEvents[0]->playerBonusId()->equals(
            PlayerBonusId::fromString(self::BONUS_ID),
        ));
    }
}
