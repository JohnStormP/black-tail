<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Command\ExpirePlayerBonusCommand;
use App\Bonus\Application\Command\GrantPlayerBonusCommand;
use App\Bonus\Application\Handler\ActivateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Application\Handler\ExpirePlayerBonusHandler;
use App\Bonus\Application\Handler\GrantPlayerBonusHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Exception\InvalidPlayerBonusTransitionException;
use App\Bonus\Domain\Exception\PlayerBonusNotFoundException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExpirePlayerBonusHandlerTest extends KernelTestCase
{
    private const string PLAYER_BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string EXPIRES_AT = '2099-01-15T12:00:00+00:00';

    private ExpirePlayerBonusHandler $expire;
    private GrantPlayerBonusHandler $grant;
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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
        $entityManager->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Casino Alpha'));
        $players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));

        $createCampaign = new CreateBonusCampaignHandler($campaigns, $tenants);
        $activateCampaign = new ActivateBonusCampaignHandler($campaigns);
        ($createCampaign)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::TENANT_ID,
            'Welcome bonus',
            1000,
            'USD',
            5,
        ));
        ($activateCampaign)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        $this->grant = new GrantPlayerBonusHandler($this->playerBonuses, $campaigns, $players);

        $wallets = self::getContainer()->get(\App\Wallet\Domain\Repository\WalletRepositoryInterface::class);
        self::assertInstanceOf(\App\Wallet\Domain\Repository\WalletRepositoryInterface::class, $wallets);
        $ledger = self::getContainer()->get(\App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface::class);
        self::assertInstanceOf(\App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface::class, $ledger);
        $transactions = self::getContainer()->get(\App\Shared\Application\TransactionManagerInterface::class);
        self::assertInstanceOf(\App\Shared\Application\TransactionManagerInterface::class, $transactions);
        $this->expire = new ExpirePlayerBonusHandler($this->playerBonuses, $wallets, $ledger, $transactions);
    }

    public function testExpiresWhenDue(): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
            self::EXPIRES_AT,
        ));

        ($this->expire)(new ExpirePlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::EXPIRES_AT,
        ));

        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertSame(PlayerBonusStatus::Expired, $bonus->status());
    }

    public function testRejectsExpireBeforeDue(): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
            self::EXPIRES_AT,
        ));

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->expire)(new ExpirePlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            '2099-01-14T12:00:00+00:00',
        ));
    }

    public function testRejectsExpireWithoutExpiresAt(): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->expire)(new ExpirePlayerBonusCommand(self::PLAYER_BONUS_ID));
    }

    public function testRejectsUnknownBonus(): void
    {
        $this->expectException(PlayerBonusNotFoundException::class);

        ($this->expire)(new ExpirePlayerBonusCommand('01EXYZ00000000000000000003'));
    }

    public function testRejectsExpireWhenCancelled(): void
    {
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
            self::EXPIRES_AT,
        ));
        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $bonus->cancel();
        $this->playerBonuses->save($bonus);

        $this->expectException(InvalidPlayerBonusTransitionException::class);

        ($this->expire)(new ExpirePlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            '2099-01-16T12:00:00+00:00',
        ));
    }
}
