<?php

namespace App\Tests\Player\Application\Handler;

use App\Player\Application\Command\RegisterPlayerCommand;
use App\Player\Application\Handler\RegisterPlayerHandler;
use App\Player\Domain\Entity\Player;
use App\Player\Domain\Exception\InvalidPlayerEmailException;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterPlayerHandlerTest extends KernelTestCase
{
    private RegisterPlayerHandler $handler;
    private PlayerRepositoryInterface $players;

    protected function setUp(): void
    {
        self::bootKernel();

        $players = self::getContainer()->get(PlayerRepositoryInterface::class);
        self::assertInstanceOf(PlayerRepositoryInterface::class, $players);
        $this->players = $players;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager1 = $entityManager;

        $entityManager1->createQuery('DELETE FROM '.Player::class)->execute();

        $this->handler = new RegisterPlayerHandler($this->players);
    }

    public function testRegistersPlayer(): void
    {
        $playerId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $tenantId = '01BX5ZZKBKACTAV9WEVGEMMVRY';

        ($this->handler)(new RegisterPlayerCommand($playerId, $tenantId, 'Player@Example.COM'));

        $player = $this->players->getById(PlayerId::fromString($playerId));
        $this->assertSame('player@example.com', $player->email()->toString());
        $this->assertSame($tenantId, $player->tenantId()->toString());
        $this->assertTrue($player->isActive());
    }

    public function testRejectsInvalidPlayerId(): void
    {
        $this->expectException(InvalidPlayerIdException::class);

        ($this->handler)(new RegisterPlayerCommand(
            'not-a-ulid',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'player@example.com',
        ));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidPlayerEmailException::class);

        ($this->handler)(new RegisterPlayerCommand(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'not-an-email',
        ));
    }
}
