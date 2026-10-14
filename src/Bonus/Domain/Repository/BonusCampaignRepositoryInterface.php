<?php

namespace App\Bonus\Domain\Repository;

use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Exception\BonusCampaignNotFoundException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;

interface BonusCampaignRepositoryInterface
{
    public function save(BonusCampaign $campaign): void;

    public function findById(BonusCampaignId $id): ?BonusCampaign;

    /**
     * @throws BonusCampaignNotFoundException
     */
    public function getById(BonusCampaignId $id): BonusCampaign;
}
