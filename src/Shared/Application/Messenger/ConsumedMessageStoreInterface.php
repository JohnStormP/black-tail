<?php

namespace App\Shared\Application\Messenger;

interface ConsumedMessageStoreInterface
{
    public function claim(string $consumer, string $messageKey): bool;
}
