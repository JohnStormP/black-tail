<?php

namespace App\Bonus\Application\Command;

final readonly class ActivateBonusCampaignCommand
{
    public function __construct(
        public string $campaignId,
    ) {
    }
}
