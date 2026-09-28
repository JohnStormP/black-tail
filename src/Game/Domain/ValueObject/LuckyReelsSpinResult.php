<?php

namespace App\Game\Domain\ValueObject;

/**
 * @phpstan-type Grid list<list<ReelSymbol>>
 */
final readonly class LuckyReelsSpinResult
{
    /**
     * @param list<int>               $stops
     * @param list<list<ReelSymbol>>  $grid
     * @param list<LuckyReelsLineWin> $lineWins
     */
    public function __construct(
        private array $stops,
        private array $grid,
        private array $lineWins,
        private int $totalMultiplier,
    ) {
    }

    /**
     * @return list<int>
     */
    public function stops(): array
    {
        return $this->stops;
    }

    /**
     * @return list<list<ReelSymbol>>
     */
    public function grid(): array
    {
        return $this->grid;
    }

    /**
     * @return list<LuckyReelsLineWin>
     */
    public function lineWins(): array
    {
        return $this->lineWins;
    }

    public function totalMultiplier(): int
    {
        return $this->totalMultiplier;
    }

    public function isWin(): bool
    {
        return $this->totalMultiplier > 0;
    }

    public function winAmountMinor(int $betMinor): int
    {
        return $betMinor * $this->totalMultiplier;
    }
}
