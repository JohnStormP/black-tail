import type { RoleId } from './permissions'

/**
 * Maps Symfony security roles from /api/me to console RoleId.
 */
export function mapBackendRole(roles: string[]): RoleId {
  if (roles.includes('ROLE_SUPER_ADMIN')) return 'platform_admin'
  if (roles.includes('ROLE_TENANT_ADMIN')) return 'tenant_admin'
  if (roles.includes('ROLE_ANALYST')) return 'risk_analyst'
  if (roles.includes('ROLE_SUPPORT')) return 'support'
  return 'support'
}
