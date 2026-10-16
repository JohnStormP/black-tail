<?php

namespace App\Tests\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Application\Command\GrantPlayerBonusCommand;
use App\Bonus\Application\Handler\ActivateBonusCampaignHandler;
use App\Bonus\Application\Handler\CreateBonusCampaignHandler;
use App\Bonus\Application\Handler\GrantPlayerBonusHandler;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Exception\PlayerBonusAlreadyGrantedException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GrantPlayerBonusHandlerTest extends KernelTestCase
{
    private const string PLAYER_BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';
    private const string OTHER_TENANT_ID = '01EXYZ00000000000000000003';

    private GrantPlayerBonusHandler $grant;
    private CreateBonusCampaignHandler $createCampaign;
    private ActivateBonusCampaignHandler $activateCampaign;
    private PlayerBonusRepositoryInterface $playerBonuses;
    private PlayerRepositoryInterface $players;
    private EntityManagerInterface $entityManager;

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

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $entityManager->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
        $entityManager->createQuery('DELETE FROM '.BonusCampaign::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();
        $entityManager->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants->save(Tenant::register(TenantId::fromString(self::TENANT_ID), 'Casino Alpha'));
        $tenants->save(Tenant::register(TenantId::fromString(self::OTHER_TENANT_ID), 'Casino Beta'));

        $this->players->save(Player::register(
            PlayerId::fromString(self::PLAYER_ID),
            TenantId::fromString(self::TENANT_ID),
            PlayerEmail::fromString('player@example.com'),
        ));

        $this->createCampaign = new CreateBonusCampaignHandler($campaigns, $tenants);
        $this->activateCampaign = new ActivateBonusCampaignHandler($campaigns);
        $this->grant = new GrantPlayerBonusHandler($this->playerBonuses, $campaigns, $this->players);
    }

    public function testGrantsActivePlayerBonusFromActiveCampaign(): void
    {
        $this->seedActiveCampaign();

        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));

        $bonus = $this->playerBonuses->getById(PlayerBonusId::fromString(self::PLAYER_BONUS_ID));
        $this->assertTrue($bonus->playerId()->equals(PlayerId::fromString(self::PLAYER_ID)));
        $this->assertSame(1000, $bonus->grantedAmount()->amount());
        $this->assertSame(5000, $bonus->wageringRequired()->amount());
        $this->assertSame(0, $bonus->wageringProgress()->amount());
        $this->assertSame(PlayerBonusStatus::Active, $bonus->status());
    }

    public function testGrantIsIdempotentForSameIds(): void
    {
        $this->seedActiveCampaign();
        $command = new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        );

        ($this->grant)($command);
        ($this->grant)($command);

        $count = (int) $this->entityManager->createQuery(
            'SELECT COUNT(b.id) FROM '.PlayerBonus::class.' b'
        )->getSingleScalarResult();
        $this->assertSame(1, $count);
    }

    public function testRejectsSecondGrantWithDifferentBonusId(): void
    {
        $this->seedActiveCampaign();
        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));

        $this->expectException(PlayerBonusAlreadyGrantedException::class);

        ($this->grant)(new GrantPlayerBonusCommand(
            '01FXYZ00000000000000000004',
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));
    }

    public function testRejectsDraftCampaign(): void
    {
        ($this->createCampaign)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::TENANT_ID,
            'Welcome bonus',
            1000,
            'USD',
            5,
        ));

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));
    }

    public function testRejectsInactivePlayer(): void
    {
        $this->seedActiveCampaign();
        $player = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $player->suspend();
        $this->players->save($player);

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));
    }

    public function testRejectsTenantMismatch(): void
    {
        ($this->createCampaign)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::OTHER_TENANT_ID,
            'Welcome bonus',
            1000,
            'USD',
            5,
        ));
        ($this->activateCampaign)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));

        $this->expectException(InvalidPlayerBonusException::class);

        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            self::PLAYER_ID,
            self::CAMPAIGN_ID,
        ));
    }

    public function testRejectsUnknownPlayer(): void
    {
        $this->seedActiveCampaign();

        $this->expectException(PlayerNotFoundException::class);

        ($this->grant)(new GrantPlayerBonusCommand(
            self::PLAYER_BONUS_ID,
            '01GXYZ00000000000000000005',
            self::CAMPAIGN_ID,
        ));
    }

    private function seedActiveCampaign(): void
    {
        ($this->createCampaign)(new CreateBonusCampaignCommand(
            self::CAMPAIGN_ID,
            self::TENANT_ID,
            'Welcome bonus',
            1000,
            'USD',
            5,
        ));
        ($this->activateCampaign)(new ActivateBonusCampaignCommand(self::CAMPAIGN_ID));
    }
}
