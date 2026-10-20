<?php

namespace App\Tests\Shared\Doubles;

use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;

final class InMemoryConsumedMessageStore implements ConsumedMessageStoreInterface
{
    /** @var array<string, true> */
    private array $claimed = [];

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
