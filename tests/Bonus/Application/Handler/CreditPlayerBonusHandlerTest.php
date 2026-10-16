<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Command\CreditPlayerBonusCommand;
use App\Bonus\Application\Command\GrantPlayerBonusCommand;
use App\Bonus\Application\Handler\ActivateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreditPlayerBonusHandler;
use App\Bonus\Application\Handler\GrantPlayerBonusHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Application\Command\OpenWalletCommand;
use App\Wallet\Application\Handler\OpenWalletHandler;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditPlayerBonusHandlerTest extends KernelTestCase
{
    private const string PLAYER_BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string OTHER_PLAYER_ID = '01HXYZ00000000000000000006';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string OTHER_CAMPAIGN_ID = '01JXYZ00000000000000000007';
    private const string OTHER_BONUS_ID = '01KXYZ00000000000000000008';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string WALLET_ID = '01EXYZ00000000000000000003';
    private const string OTHER_WALLET_ID = '01FXYZ00000000000000000004';
    private const string CREDIT_ENTRY_ID = '01GXYZ00000000000000000005';

    private CreditPlayerBonusHandler $credit;
    private CreateBonusCampaignHandler $createCampaign;
    private ActivateBonusCampaignHandler $activateCampaign;
    private GrantPlayerBonusHandler $grant;
    private OpenWalletHandler $open;
    private WalletRepositoryInterface $wallets;
    private LedgerEntryRepositoryInterface $ledger;
    private PlayerBonusRepositoryInterface $playerBonuses;
    private PlayerRepositoryInterface $players;

    protected function setUp(): void
    {
        self::bootKernel();

        $playerBonuses = self::getContainer()->get(PlayerBonusRepositoryInterface::class);
        self::assertInstanceOf(PlayerBonusRepositoryInterface::class, $playerBonuses);
        $this->playerBonuses = $playerBonuses;

        $campaigns = self::getContainer()->get(BonusCampaignRepositoryInterface::class);
        self::assertInstanceOf(BonusCampaignRepositoryInterface::class, $campaigns);

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);
        $this->ledger = $ledger;

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $entityManager->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
        $entityManager->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Wallet::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Casino Alpha'));
        $this->players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));

        $this->createCampaign = new CreateBonusCampaignHandler($campaigns, $tenants);
        $this->activateCampaign = new ActivateBonusCampaignHandler($campaigns);
        $this->grant = new GrantPlayerBonusHandler($this->playerBonuses, $campaigns, $this->players);
        $this->open = new OpenWalletHandler($this->wallets);
        $this->credit = new CreditPlayerBonusHandler(
            $this->playerBonuses,
            $this->wallets,
            $this->ledger,
            $transactions,
        );

        $this->seedActiveCampaign(self::CAMPAIGN_ID, 1000);
    }

    public function testCreditsGrantedAmountToWallet(): void
    {
        $this->seedGrantedBonusAndWallet();

        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $wallet->availableBalance()->amount());

        $entry = $this->ledger->getById(LedgerEntryId::fromString(self::CREDIT_ENTRY_ID));
        $this->assertSame(LedgerEntryType::BonusCredit, $entry->type());
        $this->assertSame(1000, $entry->amount()->amount());
        $this->assertSame(1000, $entry->balanceAfter()->amount());
    }

    public function testCreditIsIdempotentForSameEntry(): void
    {
        $this->seedGrantedBonusAndWallet();
        $command = new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        );

        ($this->credit)($command);
        ($this->credit)($command);

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $this->assertSame(1000, $wallet->availableBalance()->amount());
    }

    public function testRejectsLedgerIdempotencyConflict(): void
    {
        $this->seedGrantedBonusAndWallet();
        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        $this->seedActiveCampaign(self::OTHER_CAMPAIGN_ID, 2500);
        ($this->grant)(new GrantPlayerBonusCommand(
            self::OTHER_BONUS_ID,
            self::PLAYER_ID,
            self::OTHER_CAMPAIGN_ID,
        ));

        $this->expectException(LedgerIdempotencyConflictException::class);

        ($this->credit)(new CreditPlayerBonusCommand(
            self::OTHER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));
    }

    public function testRejectsWalletOfAnotherPlayer(): void
    {
        $this->seedGrantedBonusAndWallet();
        $this->players->save(Player::register(
            PlayerId::fromString(self::OTHER_PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('other@example.com'),
        ));
        ($this->open)(new OpenWalletCommand(self::OTHER_WALLET_ID, self::OTHER_PLAYER_ID, 'USD'));

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::OTHER_WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));
    }

    public function testRejectsCancelledBonus(): void
    {
        $this->seedGrantedBonusAndWallet();
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $bonus->cancel();
        $this->playerBonuses->save($bonus);

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));
    }

    private function seedActiveCampaign(string $campaignId, int $bonusAmountMinor): void
    {
        ($this->createCampaign)(new CreateBonusCampaignCommand(
            $campaignId,
            self::TENANT_ID,
            'Welcome bonus '.$campaignId,
            $bonusAmountMinor,
            'USD',
            5,
        ));
        ($this->activateCampaign)(new ActivateBonusCampaignCommand($campaignId));
    }

    private function seedGrantedBonusAndWallet(): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
    }
}
