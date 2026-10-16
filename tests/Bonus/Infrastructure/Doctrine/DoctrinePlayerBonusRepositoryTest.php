<?php

namespace App\Tests\Bonus\Infrastructure\Doctrine;

use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\PlayerBonusNotFoundException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Player\Domain\ValueObject\PlayerId;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePlayerBonusRepositoryTest extends KernelTestCase
{
    private const string BONUS_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string PLAYER_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string CAMPAIGN_ID = '01CXYZ00000000000000000001';
    private const string TENANT_ID = '01DXYZ00000000000000000002';

    private PlayerBonusRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $repository = self::getContainer()->get(PlayerBonusRepositoryInterface::class);
        self::assertInstanceOf(PlayerBonusRepositoryInterface::class, $repository);
        $this->repository = $repository;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.PlayerBonus::class)->execute();
    }

    public function testSaveFindByIdAndFindByPlayerAndCampaign(): void
    {
        $id = PlayerBonusId::fromString(self::BONUS_ID);
        $playerId = PlayerId::fromString(self::PLAYER_ID);
        $campaignId = BonusCampaignId::fromString(self::CAMPAIGN_ID);

        $this->repository->save($this->newBonus($id, $playerId, $campaignId));

        $byId = $this->repository->findById($id);
        $this->assertNotNull($byId);
        $this->assertTrue($byId->id()->equals($id));
        $this->assertTrue($byId->playerId()->equals($playerId));
        $this->assertTrue($byId->campaignId()->equals($campaignId));
        $this->assertSame(1000, $byId->grantedAmount()->amount());
        $this->assertSame(Currency::Usd, $byId->grantedAmount()->currency());
        $this->assertSame(5000, $byId->wageringRequired()->amount());
        $this->assertSame(0, $byId->wageringProgress()->amount());
        $this->assertSame(PlayerBonusStatus::Active, $byId->status());

        $byPair = $this->repository->findByPlayerAndCampaign($playerId, $campaignId);
        $this->assertNotNull($byPair);
        $this->assertTrue($byPair->id()->equals($id));

        $loaded = $this->repository->getById($id);
        $this->assertTrue($loaded->id()->equals($id));
    }

    public function testGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(PlayerBonusNotFoundException::class);

        $this->repository->getById(PlayerBonusId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }

    public function testFindByPlayerAndCampaignReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repository->findByPlayerAndCampaign(
            PlayerId::fromString(self::PLAYER_ID),
            BonusCampaignId::fromString(self::CAMPAIGN_ID),
        ));
    }

    private function newBonus(
        PlayerBonusId $id,
        PlayerId $playerId,
        BonusCampaignId $campaignId,
    ): PlayerBonus {
        return PlayerBonus::grant(
            $id,
            $playerId,
            $campaignId,
            TenantId::fromString(self::TENANT_ID),
            Money::of(1000, Currency::Usd),
            Money::of(5000, Currency::Usd),
        );
    }
}
