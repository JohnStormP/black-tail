<?php

namespace App\Betting\Domain\Repository;

use App\Betting\Domain\Entity\Bet;
use App\Betting\Domain\Exception\BetNotFoundException;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetIdempotencyKey;

interface BetRepositoryInterface
{
    public function save(Bet $bet): void;

    public function findById(BetId $id): ?Bet;

    /**
     * @throws BetNotFoundException
     */
    public function getById(BetId $id): Bet;

    public function findByIdempotencyKey(BetIdempotencyKey $key): ?Bet;
}
