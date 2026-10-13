<?php

namespace App\Bonus\Domain\Exception;

use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Shared\Domain\Exception\DomainException;

final class BonusCampaignNotFoundException extends DomainException
{
    public static function withId(BonusCampaignId $id): self
    {
        return new self('Bonus campaign with id "'.$id->toString().'" cannot be found');
    }
}
