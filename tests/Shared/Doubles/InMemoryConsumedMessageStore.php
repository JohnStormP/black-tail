<?php

namespace App\Tests\Shared\Doubles;

use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;

final class InMemoryConsumedMessageStore implements ConsumedMessageStoreInterface
{
    /** @var array<string, true> */
    private array $claimed = [];

    public function has(string $consumer, string $messageKey): bool
    {
        return isset($this->claimed[$consumer."\0".$messageKey]);
    }

    public function claim(string $consumer, string $messageKey): bool
    {
        $key = $consumer."\0".$messageKey;
        if (isset($this->claimed[$key])) {
            return false;
        }

        $this->claimed[$key] = true;

        return true;
    }
}
