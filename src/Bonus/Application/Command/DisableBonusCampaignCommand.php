<?php

namespace App\Bonus\Application\Command;

final readonly class DisableBonusCampaignCommand
{
    public function __construct(
        public string $campaignId,
    ) {
    }
}
