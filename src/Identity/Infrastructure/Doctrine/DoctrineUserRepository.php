<?php

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\UserNotFoundException;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserRepositoryInterface::class, public: true)]
final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function findById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id->toString());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getById(UserId $id): User
    {
        $user = $this->findById($id);
        if (null === $user) {
            throw UserNotFoundException::withId($id);
        }

        return $user;
    }

    public function findByEmail(UserEmail $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(
            [
                'email' => $email->toString(),
            ]
        );
    }
}
