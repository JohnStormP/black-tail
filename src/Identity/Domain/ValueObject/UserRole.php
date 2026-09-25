<?php

namespace App\Identity\Domain\ValueObject;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case TenantAdmin = 'tenant_admin';
    case Support = 'support';
    case Analyst = 'analyst';
}
