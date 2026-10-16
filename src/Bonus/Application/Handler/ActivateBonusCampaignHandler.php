<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\ActivateBonusCampaignCommand;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;

final class ActivateBonusCampaignHandler
{
    public function __construct(
        private readonly BonusCampaignRepositoryInterface $campaigns,
    ) {
    }

    public function __invoke(ActivateBonusCampaignCommand $command): void
    {
        $id = BonusCampaignId::fromString($command->campaignId);
        $campaign = $this->campaigns->getById($id);
        $campaign->activate();
        $this->campaigns->save($campaign);
    }
}
