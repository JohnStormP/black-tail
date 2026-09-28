<?php

namespace App\Game\Domain\Entity;

use App\Game\Domain\Exception\InvalidGameRoundException;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Game\Domain\ValueObject\LuckyReelsSpinResult;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'game_rounds')]
class GameRound
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'game_id', type: 'string', length: 26)]
    private string $gameId;

    #[ORM\Column(name: 'player_id', type: 'string', length: 26)]
    private string $playerId;

    #[ORM\Column(name: 'bet_minor', type: 'integer')]
    private int $betMinor;

    /** @var list<int> */
    #[ORM\Column(name: 'stops', type: 'json')]
    private array $stops;

    #[ORM\Column(name: 'total_multiplier', type: 'integer')]
    private int $totalMultiplier;

    #[ORM\Column(name: 'win_minor', type: 'integer')]
    private int $winMinor;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function record(
        GameRoundId $id,
        GameId $gameId,
        PlayerId $playerId,
        int $betMinor,
        LuckyReelsConfig $config,
        LuckyReelsSpinResult $result,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if (!$config->isBetAllowed($betMinor)) {
            throw InvalidGameRoundException::betNotAllowed($betMinor);
        }
        $record = new self();
        $record->id = $id->toString();
        $record->gameId = $gameId->toString();
        $record->playerId = $playerId->toString();
        $record->betMinor = $betMinor;
        $record->stops = $result->stops();
        $record->totalMultiplier = $result->totalMultiplier();
        $record->winMinor = $result->winAmountMinor($betMinor);
        $record->createdAt = $createdAt;

        return $record;
    }

    public function id(): GameRoundId
    {
        return GameRoundId::fromString($this->id);
    }

    public function gameId(): GameId
    {
        return GameId::fromString($this->gameId);
    }

    public function playerId(): PlayerId
    {
        return PlayerId::fromString($this->playerId);
    }

    public function betMinor(): int
    {
        return $this->betMinor;
    }

    /**
     * @return list<int>
     */
    public function stops(): array
    {
        return $this->stops;
    }

    public function totalMultiplier(): int
    {
        return $this->totalMultiplier;
    }

    public function winMinor(): int
    {
        return $this->winMinor;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isWin(): bool
    {
        return $this->winMinor > 0;
    }
}
