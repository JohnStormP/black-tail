<?php

namespace App\Game\Domain\Provider;

use App\Game\Domain\ValueObject\GameCode;
use App\Game\Domain\ValueObject\LuckyReelsSpinResult;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.game_provider')]
interface GameProviderInterface
{
    public function supports(GameCode $code): bool;

    public function spin(RandomIntGeneratorInterface $rng): LuckyReelsSpinResult;
}
