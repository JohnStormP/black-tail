<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Domain\Exception\DomainException;
use App\Tenant\Domain\ValueObject\TenantId;

final class InvalidPlayerBonusException extends DomainException
{
    public static function nonPositiveGrantedAmount(): self
    {
        return new self('Player bonus granted amount must be greater than zero');
    }

    public static function nonPositiveWageringRequired(): self
    {
        return new self('Player bonus wagering required must be greater than zero');
    }

    public static function currencyMismatch(): self
    {
        return new self('Player bonus granted amount and wagering required must use the same currency');
    }

    public static function nonPositiveWageringAmount(): self
    {
        return new self('Wagering amount must be greater than zero');
    }

    public static function playerNotActive(PlayerId $playerId): self
    {
        return new self('Player with id "'.$playerId->toString().'" is not active and cannot receive a bonus');
    }

    public static function campaignNotActive(BonusCampaignId $campaignId): self
    {
        return new self('Bonus campaign with id "'.$campaignId->toString().'" is not active');
    }

    public static function tenantMismatch(PlayerId $playerId, BonusCampaignId $campaignId, TenantId $playerTenantId, TenantId $campaignTenantId): self
    {
        return new self(
            'Player "'.$playerId->toString().'" tenant "'.$playerTenantId->toString()
            .'" does not match bonus campaign "'.$campaignId->toString().'" tenant "'.$campaignTenantId->toString().'"'
        );
    }
}
