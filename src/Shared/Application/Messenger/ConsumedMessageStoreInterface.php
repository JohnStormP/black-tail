<?php

namespace App\Shared\Application\Messenger;

interface ConsumedMessageStoreInterface
{
    public function has(string $consumer, string $messageKey): bool;

    public function claim(string $consumer, string $messageKey): bool;
}
