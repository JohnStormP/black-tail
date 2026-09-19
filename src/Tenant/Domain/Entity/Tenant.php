<?php

namespace App\Tenant\Domain\Entity;

use App\Tenant\Domain\Exception\InvalidTenantNameException;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: TenantStatus::class)]
    private TenantStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function register(TenantId $id, string $name, \DateTimeImmutable $createdAt = new \DateTimeImmutable()): self
    {
        $tenant = new self();
        $tenant->id = $id->toString();
        $tenant->name = self::normalizeName($name);
        $tenant->status = TenantStatus::Active;
        $tenant->createdAt = $createdAt;

        return $tenant;
    }

    public function id(): TenantId
    {
        return TenantId::fromString($this->id);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = self::normalizeName($name);
    }

    public function suspend(): void
    {
        $this->status = TenantStatus::Suspended;
    }

    public function activate(): void
    {
        $this->status = TenantStatus::Active;
    }

    public function isActive(): bool
    {
        return TenantStatus::Active === $this->status;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            throw InvalidTenantNameException::empty();
        }

        return $name;
    }
}
