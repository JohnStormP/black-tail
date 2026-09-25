<?php

namespace App\Identity\Application\Command;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $password,
        public string $role,
        public ?string $tenantId = null,
    ) {
    }
}
