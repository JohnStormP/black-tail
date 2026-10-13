<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Command\PlaceBetCommand;
use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\BetIdempotencyConflictException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class PlaceBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
        private readonly PlayerRepositoryInterface $players,
        private readonly GameRepositoryInterface $games,
        private readonly TenantGameRepositoryInterface $tenantGames,
    ) {
    }

    public function __invoke(PlaceBetCommand $command): void
    {
        $betId = BetId::fromString($command->betId);
        $playerId = PlayerId::fromString($command->playerId);
        $gameId = GameId::fromString($command->gameId);
        $roundId = GameRoundId::fromString($command->roundId);
        $idempotencyKey = BetIdempotencyKey::fromString($command->idempotencyKey);
        $amount = Money::of($command->amountMinor, Currency::from($command->currency));

        $player = $this->players->getById($playerId);
        $game = $this->games->getById($gameId);

        if (!$game->isActive()) {
            throw GameNotPlayableException::disabled($gameId);
        }

        $tenantId = $player->tenantId();
        $link = $this->tenantGames->findByTenantAndGame($tenantId, $gameId);

        if (null === $link) {
            throw GameNotPlayableException::notAssignedToTenant($gameId, $tenantId);
        }

        if (!$link->isEnabled()) {
            throw GameNotPlayableException::disabledForTenant($gameId, $tenantId);
        }

        $existing = $this->bets->findByIdempotencyKey($idempotencyKey);
        if (null !== $existing) {
            if ($this->matchesRequest($existing, $betId, $playerId, $gameId, $roundId, $amount)) {
                return;
            }

            throw BetIdempotencyConflictException::forKey($idempotencyKey);
        }

        $bet = Bet::place(
            $betId,
            $playerId,
            $gameId,
            $roundId,
            $amount,
            $idempotencyKey,
            $command->providerReference,
        );

        try {
            $this->bets->save($bet);
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }

    private function matchesRequest(
        Bet $existing,
        BetId $betId,
        PlayerId $playerId,
        GameId $gameId,
        GameRoundId $roundId,
        Money $amount,
    ): bool {
        return $existing->id()->equals($betId)
            && $existing->playerId()->equals($playerId)
            && $existing->gameId()->equals($gameId)
            && $existing->roundId()->equals($roundId)
            && $existing->amount()->equals($amount);
    }
}
