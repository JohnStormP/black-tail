import type { MeResponse } from '@/api/authApi'
import type { TenantResponse } from '@/api/tenantsApi'
import { mapBackendRole } from './mapBackendRole'
import type { AuthUser } from './types'

export function toAuthUser(me: MeResponse, tenant: TenantResponse | null): AuthUser {
  const local = me.email.includes('@') ? me.email.split('@')[0]! : me.email
  return {
    id: me.id,
    name: local,
    email: me.email,
    role: mapBackendRole(me.roles),
    tenantId: me.tenantId,
    tenant: tenant?.name ?? me.tenantId ?? 'Platform',
  }
}
