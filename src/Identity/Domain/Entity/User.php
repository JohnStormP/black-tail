<?php

namespace App\Identity\Domain\Entity;

use App\Identity\Domain\Exception\InvalidUserException;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserRole;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
class User
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'email', type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 255)]
    private string $passwordHash;

    #[ORM\Column(name: 'role', type: 'string', length: 32, enumType: UserRole::class)]
    private UserRole $role;

    #[ORM\Column(name: 'tenant_id', type: 'string', length: 26, nullable: true)]
    private ?string $tenantId;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: UserStatus::class)]
    private UserStatus $status;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    public static function register(
        UserId $id,
        UserEmail $email,
        string $passwordHash,
        UserRole $role,
        ?TenantId $tenantId = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        if ('' === trim($passwordHash)) {
            throw InvalidUserException::emptyPasswordHash();
        }

        if (UserRole::SuperAdmin === $role) {
            if (null !== $tenantId) {
                throw InvalidUserException::superAdminMustNotHaveTenant();
            }
        } elseif (null === $tenantId) {
            throw InvalidUserException::roleRequiresTenant();
        }

        $user = new self();
        $user->id = $id->toString();
        $user->email = $email->toString();
        $user->passwordHash = $passwordHash;
        $user->role = $role;
        $user->tenantId = $tenantId?->toString();
        $user->status = UserStatus::Active;
        $user->createdAt = $createdAt;

        return $user;
    }

    public function id(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function email(): UserEmail
    {
        return UserEmail::fromString($this->email);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function tenantId(): ?TenantId
    {
        return null === $this->tenantId ? null : TenantId::fromString($this->tenantId);
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    public function disable(): void
    {
        $this->status = UserStatus::Disabled;
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        if ('' === trim($passwordHash)) {
            throw InvalidUserException::emptyPasswordHash();
        }

        $this->passwordHash = $passwordHash;
    }
}
