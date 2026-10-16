<?php

namespace App\Bonus\Domain\Repository;

use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\PlayerBonusNotFoundException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Player\Domain\ValueObject\PlayerId;

interface PlayerBonusRepositoryInterface
{
    public function save(PlayerBonus $bonus): void;

    public function findById(PlayerBonusId $id): ?PlayerBonus;

    /**
     * @throws PlayerBonusNotFoundException
     */
    public function getById(PlayerBonusId $id): PlayerBonus;

    public function findByPlayerAndCampaign(PlayerId $playerId, BonusCampaignId $campaignId): ?PlayerBonus;
}
