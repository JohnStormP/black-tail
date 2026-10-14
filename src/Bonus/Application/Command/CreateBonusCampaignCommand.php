<?php

namespace App\Bonus\Application\Command;

final readonly class CreateBonusCampaignCommand
{
    public function __construct(
        public string $campaignId,
        public string $tenantId,
        public string $name,
        public int $bonusAmountMinor,
        public string $currency,
        public int $wageringMultiplier,
    ) {
    }
}
