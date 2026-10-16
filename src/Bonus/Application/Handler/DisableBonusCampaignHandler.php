<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\DisableBonusCampaignCommand;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;

final class DisableBonusCampaignHandler
{
    public function __construct(
        private readonly BonusCampaignRepositoryInterface $campaigns,
    ) {
    }

    public function __invoke(DisableBonusCampaignCommand $command): void
    {
        $id = BonusCampaignId::fromString($command->campaignId);
        $campaign = $this->campaigns->getById($id);
        $campaign->disable();
        $this->campaigns->save($campaign);
    }
}
