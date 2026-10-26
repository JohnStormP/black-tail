<?php

namespace App\Betting\Application\Handler;

use App\Betting\Application\Query\GetBetQuery;
use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;

final class GetBetHandler
{
    public function __construct(
        private readonly BetRepositoryInterface $bets,
    ) {
    }

    public function __invoke(GetBetQuery $query): Bet
    {
        return $this->bets->getById(BetId::fromString($query->betId));
    }
}
