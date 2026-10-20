<?php

namespace App\Risk\Infrastructure\Messenger;

use App\Risk\Application\Message\LogRiskSignalRaisedMessage;
use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LogRiskSignalRaisedMessageHandler
{
    public const CONSUMER = 'log_risk_signal_raised';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConsumedMessageStoreInterface $consumedMessages,
    ) {
    }

    public function __invoke(LogRiskSignalRaisedMessage $message): void
    {
        $messageKey = implode('|', [
            $message->tenantId,
            $message->playerId,
            $message->operation,
            $message->action,
            implode(',', $message->matchedRuleIds),
        ]);
        if ($this->consumedMessages->claim(self::CONSUMER, $messageKey)) {
            $this->logger->warning('Risk signal raised', [
                'tenant_id' => $message->tenantId,
                'player_id' => $message->playerId,
                'operation' => $message->operation,
                'action' => $message->action,
                'matched_rule_ids' => $message->matchedRuleIds,
            ]);
        }
    }
}
