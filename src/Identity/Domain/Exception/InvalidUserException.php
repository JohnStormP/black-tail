<?php

namespace App\Identity\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidUserException extends DomainException
{
    public static function emptyPassword(): self
    {
        return new self('User password cannot be empty');
    }

    public static function emptyPasswordHash(): self
    {
        return new self('User password hash cannot be empty');
    }

    public static function superAdminMustNotHaveTenant(): self
    {
        return new self('Super admin must not belong to a tenant');
    }

    public static function roleRequiresTenant(): self
    {
        return new self('This user role requires a tenant');
    }
}
