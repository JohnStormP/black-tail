<?php

namespace App\Risk\Infrastructure\Http;

use App\Risk\Domain\Entity\RiskRule;

final class RiskRuleJson
{
    /**
     * @return array{
     *     id: string,
     *     tenantId: string,
     *     name: string,
     *     type: string,
     *     threshold: int,
     *     windowSeconds: int|null,
     *     action: string,
     *     status: string,
     * }
     */
    public static function from(RiskRule $rule): array
    {
        return [
            'id' => $rule->id()->toString(),
            'tenantId' => $rule->tenantId()->toString(),
            'name' => $rule->name(),
            'type' => $rule->type()->value,
            'threshold' => $rule->threshold(),
            'windowSeconds' => $rule->windowSeconds(),
            'action' => $rule->action()->value,
            'status' => $rule->status()->value,
        ];
    }
}
