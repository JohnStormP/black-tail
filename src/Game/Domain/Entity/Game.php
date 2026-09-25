<?php

namespace App\Game\Domain\Entity;

use App\Game\Domain\Exception\InvalidGameNameException;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'games')]
#[ORM\UniqueConstraint(name: 'uniq_games_code', columns: ['code'])]
class Game
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'code', type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: GameStatus::class)]
    private GameStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function register(
        GameId $id,
        GameCode $code,
        string $name,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $game = new self();
        $name = trim($name);
        if ('' === $name) {
            throw InvalidGameNameException::empty();
        }
        $game->id = $id->toString();
        $game->code = $code->toString();
        $game->name = $name;
        $game->status = GameStatus::Active;
        $game->createdAt = $createdAt;

        return $game;
    }

    public function id(): GameId
    {
        return GameId::fromString($this->id);
    }

    public function code(): GameCode
    {
        return GameCode::fromString($this->code);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): GameStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return GameStatus::Active === $this->status;
    }

    public function disable(): void
    {
        $this->status = GameStatus::Disabled;
    }

    public function activate(): void
    {
        $this->status = GameStatus::Active;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name) {
            throw InvalidGameNameException::empty();
        }

        $this->name = $name;
    }
}
