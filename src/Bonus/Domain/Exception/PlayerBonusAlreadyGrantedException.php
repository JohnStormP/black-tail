<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Exception\DomainException;

final class PlayerBonusAlreadyGrantedException extends DomainException
{
    public static function forPlayerAndCampaign(PlayerId $playerId, BonusCampaignId $campaignId): self
    {
        return new self(
            'Player bonus for player "'.$playerId->toString()
            .'" and campaign "'.$campaignId->toString().'" has already been granted'
        );
    }
}
