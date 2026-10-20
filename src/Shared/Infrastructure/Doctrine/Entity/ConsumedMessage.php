<?php

namespace App\Shared\Infrastructure\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consumed_messages')]
class ConsumedMessage
{
    #[ORM\Id]
    #[ORM\Column(name: 'consumer', type: 'string', length: 64)]
    private string $consumer;

    #[ORM\Id]
    #[ORM\Column(name: 'message_key', type: 'string', length: 128)]
    private string $messageKey;

    #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $consumedAt;

    private function __construct()
    {
    }

    public static function record(string $consumer, string $messageKey, \DateTimeImmutable $consumedAt): self
    {
        $row = new self();
        $row->consumer = $consumer;
        $row->messageKey = $messageKey;
        $row->consumedAt = $consumedAt;

        return $row;
    }

    public function consumer(): string
    {
        return $this->consumer;
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    public function consumedAt(): \DateTimeImmutable
    {
        return $this->consumedAt;
    }
}
