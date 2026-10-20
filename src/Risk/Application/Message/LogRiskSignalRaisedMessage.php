<?php

namespace App\Risk\Application\Message;

final readonly class LogRiskSignalRaisedMessage
{
    /**
     * @param list<string> $matchedRuleIds
     */
    public function __construct(
        public string $tenantId,
        public string $playerId,
        public string $operation,
        public string $action,
        public array $matchedRuleIds,
    ) {
    }
}
