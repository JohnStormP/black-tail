<?php

namespace App\Bonus\Infrastructure\EventListener;

use App\Bonus\Domain\Event\PlayerBonusCompleted;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class LogPlayerBonusCompletedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PlayerBonusCompleted $event): void
    {
        $this->logger->info('Player bonus completed', [
            'player_bonus_id' => $event->playerBonusId()->toString(),
            'player_id' => $event->playerId()->toString(),
            'campaign_id' => $event->campaignId()->toString(),
            'granted_amount' => $event->grantedAmount()->amount(),
            'currency' => $event->grantedAmount()->currency()->value,
        ]);
    }
}
