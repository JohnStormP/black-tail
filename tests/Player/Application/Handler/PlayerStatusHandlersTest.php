<?php

namespace App\Tests\Player\Application\Handler;

use App\Player\Application\Command\ActivatePlayerCommand;
use App\Player\Application\Command\BlockPlayerCommand;
use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Command\SelfExcludePlayerCommand;
use App\Player\Application\Command\SuspendPlayerCommand;
use App\Player\Application\Handler\ActivatePlayerHandler;
use App\Player\Application\Handler\BlockPlayerHandler;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Application\Handler\SelfExcludePlayerHandler;
use App\Player\Application\Handler\SuspendPlayerHandler;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Player\Domain\ValueObject\PlayerStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PlayerStatusHandlersTest extends KernelTestCase
{
    private const string PLAYER_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';

    private RegisterPlayerHandler $register;
    private BlockPlayerHandler $block;
    private SuspendPlayerHandler $suspend;
    private ActivatePlayerHandler $activate;
    private SelfExcludePlayerHandler $selfExclude;
    private PlayerRepositoryInterface $players;

    protected function setUp(): void
    {
        self::bootKernel();

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Player::class)->execute();

        $this->register = new RegisterPlayerHandler($this->players);
        $this->block = new BlockPlayerHandler($this->players);
        $this->suspend = new SuspendPlayerHandler($this->players);
        $this->activate = new ActivatePlayerHandler($this->players);
        $this->selfExclude = new SelfExcludePlayerHandler($this->players);
    }

    public function testBlockSuspendActivateAndSelfExclude(): void
    {
        ($this->register)(new RegisterPlayerCommand(self::PLAYER_ID, self::TENANT_ID, 'player@example.com'));

        ($this->block)(new BlockPlayerCommand(self::PLAYER_ID));
        $blocked = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $this->assertSame(PlayerStatus::Blocked, $blocked->status());
        $this->assertFalse($blocked->isActive());

        ($this->suspend)(new SuspendPlayerCommand(self::PLAYER_ID));
        $suspended = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $this->assertSame(PlayerStatus::Suspended, $suspended->status());

        ($this->activate)(new ActivatePlayerCommand(self::PLAYER_ID));
        $active = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $this->assertSame(PlayerStatus::Active, $active->status());
        $this->assertTrue($active->isActive());

        ($this->selfExclude)(new SelfExcludePlayerCommand(self::PLAYER_ID));
        $excluded = $this->players->getById(PlayerId::fromString(self::PLAYER_ID));
        $this->assertSame(PlayerStatus::SelfExcluded, $excluded->status());
        $this->assertFalse($excluded->isActive());
    }

    public function testBlockUnknownPlayerFails(): void
    {
        $this->expectException(PlayerNotFoundException::class);

        ($this->block)(new BlockPlayerCommand('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
