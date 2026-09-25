<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\RegisterGameCommand;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Exception\GameAlreadyExistsException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class RegisterGameHandler
{
    public function __construct(
        private readonly GameRepositoryInterface $games,
    ) {
    }

    public function __invoke(RegisterGameCommand $command): void
    {
        $id = GameId::fromString($command->gameId);
        $code = GameCode::fromString($command->code);
        if (null !== $this->games->findByCode($code)) {
            throw GameAlreadyExistsException::withCode($code);
        }
        $game = Game::register($id, $code, $command->name);
        try {
            $this->games->save($game);
        } catch (UniqueConstraintViolationException) {
            throw GameAlreadyExistsException::withCode($code);
        }
    }
}
