<?php

namespace App\Game\Domain\Provider;

use App\Game\Domain\Exception\GameProviderNotFoundException;
use App\Game\Domain\ValueObject\GameCode;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class GameProviderResolver
{
    /**
     * @param iterable<GameProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.game_provider')]
        private readonly iterable $providers,
    ) {
    }

    public function resolve(GameCode $code): GameProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($code)) {
                return $provider;
            }
        }
        throw GameProviderNotFoundException::forCode($code);
    }
}
