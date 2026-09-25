<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Application\Handler\RegisterGameHandler;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Exception\GameAlreadyExistsException;
use App\Game\Domain\Exception\InvalidGameCodeException;
use App\Game\Domain\Exception\InvalidGameIdException;
use App\Game\Domain\Exception\InvalidGameNameException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterGameHandlerTest extends KernelTestCase
{
    private const string GAME_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private RegisterGameHandler $handler;
    private GameRepositoryInterface $games;

    protected function setUp(): void
    {
        self::bootKernel();

        $games = self::getContainer()->get(GameRepositoryInterface::class);
        self::assertInstanceOf(GameRepositoryInterface::class, $games);
        $this->games = $games;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.Game::class)->execute();

        $this->handler = new RegisterGameHandler($this->games);
    }

    public function testRegistersActiveGame(): void
    {
        ($this->handler)(new RegisterGameCommand(
            self::GAME_ID,
            'Lucky_Reels',
            'Lucky Reels',
        ));

        $game = $this->games->getById(GameId::fromString(self::GAME_ID));
        $this->assertSame('lucky_reels', $game->code()->toString());
        $this->assertSame('Lucky Reels', $game->name());
        $this->assertSame(GameStatus::Active, $game->status());
        $this->assertTrue($game->isActive());
    }

    public function testRejectsInvalidId(): void
    {
        $this->expectException(InvalidGameIdException::class);

        ($this->handler)(new RegisterGameCommand(
            'not-a-ulid',
            'lucky_reels',
            'Lucky Reels',
        ));
    }

    public function testRejectsInvalidCode(): void
    {
        $this->expectException(InvalidGameCodeException::class);

        ($this->handler)(new RegisterGameCommand(
            self::GAME_ID,
            'Lucky Reels',
            'Lucky Reels',
        ));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidGameNameException::class);

        ($this->handler)(new RegisterGameCommand(
            self::GAME_ID,
            'lucky_reels',
            '   ',
        ));
    }

    public function testRejectsDuplicateCode(): void
    {
        ($this->handler)(new RegisterGameCommand(
            self::GAME_ID,
            'lucky_reels',
            'Lucky Reels',
        ));

        $this->expectException(GameAlreadyExistsException::class);

        ($this->handler)(new RegisterGameCommand(
            '01BX5ZZKBKACTAV9WEVGEMMVRY',
            'lucky_reels',
            'Lucky Reels Copy',
        ));
    }

    public function testFindByCodeAfterRegister(): void
    {
        ($this->handler)(new RegisterGameCommand(
            self::GAME_ID,
            'lucky_reels',
            'Lucky Reels',
        ));

        $byCode = $this->games->findByCode(GameCode::fromString('lucky_reels'));
        $this->assertNotNull($byCode);
        $this->assertTrue($byCode->id()->equals(GameId::fromString(self::GAME_ID)));
    }
}
