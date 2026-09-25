<?php

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\UserEmail;
use App\Shared\Domain\Exception\DomainException;

final class UserAlreadyExistsException extends DomainException
{
    public static function withEmail(UserEmail $email): self
    {
        return new self('User with email "'.$email->toString().'" already exists');
    }
}
