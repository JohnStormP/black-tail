<?php

namespace App\Shared\Domain\Event;

interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
