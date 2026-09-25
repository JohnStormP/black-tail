<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\ValueObject\UserRole;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly array $roles,
        private readonly bool $active,
        private readonly ?string $tenantId,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            $user->id()->toString(),
            $user->email()->toString(),
            $user->passwordHash(),
            self::rolesFor($user->role()),
            $user->isActive(),
            $user->tenantId()?->toString(),
        );
    }

    /**
     * @return list<string>
     */
    public static function rolesFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => ['ROLE_SUPER_ADMIN'],
            UserRole::TenantAdmin => ['ROLE_TENANT_ADMIN'],
            UserRole::Support => ['ROLE_SUPPORT'],
            UserRole::Analyst => ['ROLE_ANALYST'],
        };
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('SecurityUser email cannot be empty');
        }

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
