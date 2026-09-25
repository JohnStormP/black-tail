<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(UserId $id): ?User;

    public function getById(UserId $id): User;

    public function findByEmail(UserEmail $email): ?User;
}
