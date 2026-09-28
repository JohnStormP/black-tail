<?php

namespace App\Game\Infrastructure\Provider;

use App\Game\Domain\Provider\GameProviderInterface;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Game\Domain\ValueObject\LuckyReelsSpinResult;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;

final class LuckyReelsGameProvider implements GameProviderInterface
{
    public function __construct(
        private readonly LuckyReelsEngine $engine,
    ) {
    }

    public function supports(GameCode $code): bool
    {
        return $code->equals(GameCode::fromString('lucky_reels'));
    }

    public function spin(RandomIntGeneratorInterface $rng): LuckyReelsSpinResult
    {
        $config = LuckyReelsConfig::default();

        return $this->engine->spin($config, $rng);
    }
}
