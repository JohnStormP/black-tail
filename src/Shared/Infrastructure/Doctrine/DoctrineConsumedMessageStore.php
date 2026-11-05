<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;
use App\Shared\Infrastructure\Doctrine\Entity\ConsumedMessage;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ConsumedMessageStoreInterface::class, public: true)]
final class DoctrineConsumedMessageStore implements ConsumedMessageStoreInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function has(string $consumer, string $messageKey): bool
    {
        return null !== $this->entityManager->find(ConsumedMessage::class, [
            'consumer' => $consumer,
            'messageKey' => $messageKey,
        ]);
    }

    public function claim(string $consumer, string $messageKey): bool
    {
        if ($this->has($consumer, $messageKey)) {
            return false;
        }

        $record = ConsumedMessage::record($consumer, $messageKey, new \DateTimeImmutable());

        try {
            $this->entityManager->persist($record);
            $this->entityManager->flush();

            return true;
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->clear();

            return false;
        }
    }
}
