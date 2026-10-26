<?php

namespace App\Tenant\Infrastructure\Http;

use App\Tenant\Domain\Entity\Tenant;

final class TenantJson
{
    /**
     * @return array{id: string, name: string, status: string}
     */
    public static function from(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id()->toString(),
            'name' => $tenant->name(),
            'status' => $tenant->status()->value,
        ];
    }
}
