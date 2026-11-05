<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Application\Command\ApplyPlayerBonusWageringCommand;
use App\Bonus\Application\Command\CancelPlayerBonusCommand;
use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Command\CreditPlayerBonusCommand;
use App\Bonus\Application\Command\ExpirePlayerBonusCommand;
use App\Bonus\Application\Command\GrantPlayerBonusCommand;
use App\Bonus\Application\Handler\ActivateBonusCampaignHandler;
use App\Bonus\Application\Handler\ApplyPlayerBonusWageringHandler;
use App\Bonus\Application\Handler\CancelPlayerBonusHandler;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreditPlayerBonusHandler;
use App\Bonus\Application\Handler\ExpirePlayerBonusHandler;
use App\Bonus\Application\Handler\GrantPlayerBonusHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
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
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class BonusMoneyModelHandlerTest extends KernelTestCase
{
    private const string PLAYER_BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FBM';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVBM';
    private const string CAMPAIGN_ID = '01CXYZ000000000000000000BM';
    private const string TENANT_ID = '01DXYZ000000000000000000BM';
    private const string WALLET_ID = '01EXYZ000000000000000000BM';
    private const string CREDIT_ENTRY_ID = '01FXYZ000000000000000000BM';
    private const string FORFEIT_ENTRY_ID = '01GXYZ000000000000000000BM';
    private const string UNLOCK_ENTRY_ID = '01HXYZ000000000000000000BM';
    private const string EXPIRES_AT = '2099-01-15T12:00:00+00:00';

    private CreditPlayerBonusHandler $credit;
    private ExpirePlayerBonusHandler $expire;
    private CancelPlayerBonusHandler $cancel;
    private ApplyPlayerBonusWageringHandler $apply;
    private GrantPlayerBonusHandler $grant;
    private OpenWalletHandler $open;
    private WalletRepositoryInterface $wallets;
    private PlayerBonusRepositoryInterface $playerBonuses;

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

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);

        $wallets = self::getContainer()->get(WalletRepositoryInterface::class);
        self::assertInstanceOf(WalletRepositoryInterface::class, $wallets);
        $this->wallets = $wallets;

        $ledger = self::getContainer()->get(LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(LedgerEntryRepositoryInterface::class, $ledger);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.LedgerEntry::class)->execute();
        $em->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
        $em->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
        $em->createQuery('DELETE FROM '.Wallet::class)->execute();
        $em->createQuery('DELETE FROM '.Player::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Bonus Casino'));
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('bonus-money@example.com'),
        ));

        $createCampaign = new CreateBonusCampaignHandler($campaigns, $tenants);
        $activateCampaign = new ActivateBonusCampaignHandler($campaigns);
        ($createCampaign)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::TENANT_ID,
            'Welcome',
            1000,
            'USD',
            1,
        ));
        ($activateCampaign)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        $this->grant = new GrantPlayerBonusHandler($playerBonuses, $campaigns, $players);
        $this->open = new OpenWalletHandler($wallets);
        $this->credit = new CreditPlayerBonusHandler($playerBonuses, $wallets, $ledger, $transactions);
        $this->expire = new ExpirePlayerBonusHandler($playerBonuses, $wallets, $ledger, $transactions);
        $this->cancel = new CancelPlayerBonusHandler($playerBonuses, $wallets, $ledger, $transactions);
        $this->apply = new ApplyPlayerBonusWageringHandler(
            $playerBonuses,
            $wallets,
            $ledger,
            $transactions,
            new EventDispatcher(),
        );
    }

    public function testCreditGoesToBonusNotAvailable(): void
    {
        $this->grantAndOpen();
        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertSame(0, $wallet->availableBalance()->amount());
        $this->assertSame(1000, $wallet->bonusBalance()->amount());
        $this->assertTrue($bonus->isCredited());
    }

    public function testExpireAfterCreditForfeitsBonusBalance(): void
    {
        $this->grantAndOpen(self::EXPIRES_AT);
        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        ($this->expire)(new ExpirePlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::EXPIRES_AT,
            self::FORFEIT_ENTRY_ID,
        ));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertSame(PlayerBonusStatus::Expired, $bonus->status());
        $this->assertSame(0, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->bonusBalance()->amount());
    }

    public function testWageringCompleteUnlocksBonusToAvailable(): void
    {
        $this->grantAndOpen();
        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        ($this->apply)(new ApplyPlayerBonusWageringCommand(
            self::PLAYER_BONUS_ID,
            1000,
            self::UNLOCK_ENTRY_ID,
        ));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertSame(PlayerBonusStatus::Completed, $bonus->status());
        $this->assertSame(1000, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->bonusBalance()->amount());
    }

    public function testCancelAfterCreditForfeitsBonusBalance(): void
    {
        $this->grantAndOpen();
        ($this->credit)(new CreditPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::WALLET_ID,
            self::CREDIT_ENTRY_ID,
        ));

        ($this->cancel)(new CancelPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::FORFEIT_ENTRY_ID,
        ));

        $wallet = $this->wallets->getById(WalletId::fromString(self::WALLET_ID));
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertSame(PlayerBonusStatus::Cancelled, $bonus->status());
        $this->assertSame(0, $wallet->availableBalance()->amount());
        $this->assertSame(0, $wallet->bonusBalance()->amount());
    }

    private function grantAndOpen(?string $expiresAt = null): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
            $expiresAt,
        ));
        ($this->open)(new OpenWalletCommand(self::WALLET_ID, self::PLAYER_ID, 'USD'));
    }
}
