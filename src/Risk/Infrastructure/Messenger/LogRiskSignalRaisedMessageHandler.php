<?php

namespace App\Risk\Infrastructure\Messenger;

use App\Risk\Application\Message\LogRiskSignalRaisedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LogRiskSignalRaisedMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogRiskSignalRaisedMessage $message): void
    {
        $this->logger->warning('Risk signal raised', [
            'tenant_id' => $message->tenantId,
            'player_id' => $message->playerId,
            'operation' => $message->operation,
            'action' => $message->action,
            'matched_rule_ids' => $message->matchedRuleIds,
        ]);
    }
}
