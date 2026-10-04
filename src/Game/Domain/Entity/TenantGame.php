<?php

namespace App\Game\Domain\Entity;

use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_games')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_games_tenant_game', columns: ['tenant_id', 'game_id'])]
class TenantGame
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'game_id', type: 'string', length: 26)]
    private string $gameId;

    #[ORM\Column(name: 'enabled', type: 'boolean')]
    private bool $enabled;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function assign(
        TenantGameId $id,
        TenantId $tenantId,
        GameId $gameId,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $link = new self();
        $link->id = $id->toString();
        $link->tenantId = $tenantId->toString();
        $link->gameId = $gameId->toString();
        $link->enabled = true;
        $link->createdAt = $createdAt;

        return $link;
    }

    public function id(): TenantGameId
    {
        return TenantGameId::fromString($this->id);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function gameId(): GameId
    {
        return GameId::fromString($this->gameId);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
