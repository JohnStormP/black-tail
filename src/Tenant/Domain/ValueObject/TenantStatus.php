<?php

namespace App\Tenant\Domain\ValueObject;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
