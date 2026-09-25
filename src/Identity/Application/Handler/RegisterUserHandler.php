<?php

namespace App\Identity\Application\Handler;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\InvalidUserException;
use App\Identity\Domain\Exception\UserAlreadyExistsException;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserRole;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class RegisterUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordHasherFactoryInterface $passwordHashers,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): void
    {
        $id = UserId::fromString($command->userId);
        $email = UserEmail::fromString($command->email);

        if ('' === trim($command->password)) {
            throw InvalidUserException::emptyPassword();
        }

        $role = UserRole::from($command->role);
        $tenantId = null !== $command->tenantId
            ? TenantId::fromString($command->tenantId)
            : null;

        if (null !== $this->users->findByEmail($email)) {
            throw UserAlreadyExistsException::withEmail($email);
        }

        $hasher = $this->passwordHashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
        $passwordHash = $hasher->hash($command->password);
        $user = User::register(
            $id,
            $email,
            $passwordHash,
            $role,
            $tenantId,
        );

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException) {
            throw UserAlreadyExistsException::withEmail($email);
        }
    }
}
