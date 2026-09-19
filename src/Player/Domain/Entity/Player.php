<?php

namespace App\Player\Domain\Entity;

use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Player\Domain\ValueObject\PlayerStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'players')]
#[ORM\UniqueConstraint(name: 'uniq_players_tenant_email', columns: ['tenant_id', 'email'])]
class Player
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26)]
    private string $tenantId;

    #[ORM\Column(name: 'email', type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: PlayerStatus::class)]
    private PlayerStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function register(
        PlayerId $id,
        TenantId $tenantId,
        PlayerEmail $email,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $player = new self();
        $player->id = $id->toString();
        $player->tenantId = $tenantId->toString();
        $player->email = $email->toString();
        $player->status = PlayerStatus::Active;
        $player->createdAt = $createdAt;

        return $player;
    }

    public function id(): PlayerId
    {
        return PlayerId::fromString($this->id);
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function email(): PlayerEmail
    {
        return PlayerEmail::fromString($this->email);
    }

    public function status(): PlayerStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function block(): void
    {
        $this->status = PlayerStatus::Blocked;
    }

    public function suspend(): void
    {
        $this->status = PlayerStatus::Suspended;
    }

    public function activate(): void
    {
        $this->status = PlayerStatus::Active;
    }

    public function selfExclude(): void
    {
        $this->status = PlayerStatus::SelfExcluded;
    }

    public function isActive(): bool
    {
        return PlayerStatus::Active === $this->status;
    }
}
