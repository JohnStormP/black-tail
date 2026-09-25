<?php

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\ValueObject\UserId;
use App\Shared\Domain\Exception\DomainException;

final class UserNotFoundException extends DomainException
{
    public static function withId(UserId $id): self
    {
        return new self('User with id "'.$id->toString().'" cannot be found');
    }
}
