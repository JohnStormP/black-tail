<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\GrantPlayerBonusCommand;
use App\Bonus\Domain\Entity\PlayerBonus;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Exception\PlayerBonusAlreadyGrantedException;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class GrantPlayerBonusHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly BonusCampaignRepositoryInterface $campaigns,
        private readonly PlayerRepositoryInterface $players,
    ) {
    }

    public function __invoke(GrantPlayerBonusCommand $command): void
    {
        $playerId = PlayerId::fromString($command->playerId);
        $playerBonusId = PlayerBonusId::fromString($command->playerBonusId);
        $bonusCampaignId = BonusCampaignId::fromString($command->campaignId);
        $player = $this->players->getById($playerId);

        if (!$player->isActive()) {
            throw InvalidPlayerBonusException::playerNotActive($playerId);
        }

        $campaign = $this->campaigns->getById($bonusCampaignId);

        if (!$campaign->isActive()) {
            throw InvalidPlayerBonusException::campaignNotActive($bonusCampaignId);
        }

        if (!$player->tenantId()->equals($campaign->tenantId())) {
            throw InvalidPlayerBonusException::tenantMismatch($playerId, $bonusCampaignId, $player->tenantId(), $campaign->tenantId());
        }

        $existing = $this->playerBonuses->findByPlayerAndCampaign($playerId, $bonusCampaignId);

        if (null !== $existing) {
            if ($existing->id()->equals($playerBonusId)) {
                return;
            }
            throw PlayerBonusAlreadyGrantedException::forPlayerAndCampaign($playerId, $bonusCampaignId);
        }

        $bonus = PlayerBonus::grant(
            $playerBonusId,
            $playerId,
            $bonusCampaignId,
            $player->tenantId(),
            $campaign->bonusAmount(),
            $campaign->requiredWager()
        );
        try {
            $this->playerBonuses->save($bonus);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->playerBonuses->findByPlayerAndCampaign($playerId, $bonusCampaignId);
            if (null !== $existing && $existing->id()->equals($playerBonusId)) {
                return;
            }
            throw PlayerBonusAlreadyGrantedException::forPlayerAndCampaign($playerId, $bonusCampaignId);
        }
    }
}
