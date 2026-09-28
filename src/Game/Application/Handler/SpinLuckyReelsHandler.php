<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\SpinLuckyReelsCommand;
use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;

final class SpinLuckyReelsHandler
{
    public function __construct(
        private readonly GameRepositoryInterface $games,
        private readonly GameRoundRepositoryInterface $rounds,
        private readonly LuckyReelsEngine $engine,
        private readonly RandomIntGeneratorInterface $rng,
    ) {
    }

    public function __invoke(SpinLuckyReelsCommand $command): void
    {
        $gameRoundId = GameRoundId::fromString($command->roundId);
        $gameId = GameId::fromString($command->gameId);
        $playerId = PlayerId::fromString($command->playerId);
        $betMinor = $command->betMinor;
        $game = $this->games->getById($gameId);

        if (!$game->isActive()) {
            throw GameNotPlayableException::disabled($gameId);
        }

        if (!GameCode::fromString('lucky_reels')->equals($game->code())) {
            throw GameNotPlayableException::unsupportedForLuckyReels($game->code());
        }

        $config = LuckyReelsConfig::default();
        $result = $this->engine->spin($config, $this->rng);
        $round = GameRound::record(
            $gameRoundId,
            $gameId,
            $playerId,
            $betMinor,
            $config,
            $result
        );

        $this->rounds->save($round);
    }
}
