<?php

namespace App\Risk\Infrastructure\EventListener;

use App\Risk\Domain\Event\RiskSignalRaised;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class LogRiskSignalRaisedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RiskSignalRaised $event): void
    {
        $this->logger->warning('Risk signal raised', [
            'tenant_id' => $event->tenantId()->toString(),
            'player_id' => $event->playerId()->toString(),
            'operation' => $event->operation()->value,
            'action' => $event->action()->value,
            'matched_rule_ids' => array_map(
                static fn ($id): string => $id->toString(),
                $event->matchedRuleIds(),
            ),
        ]);
    }
}
