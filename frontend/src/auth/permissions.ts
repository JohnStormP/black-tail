export type RoleId = 'platform_admin' | 'tenant_admin' | 'risk_analyst' | 'support'

export type Permission =
  | '*'
  | 'dashboard.read'
  | 'analytics.read'
  | 'players.read'
  | 'players.write'
  | 'transactions.read'
  | 'deposits.read'
  | 'withdrawals.read'
  | 'withdrawals.decide'
  | 'bonuses.read'
  | 'bonuses.write'
  | 'games.read'
  | 'games.write'
  | 'games.play'
  | 'games.access.write'
  | 'affiliates.read'
  | 'affiliates.write'
  | 'campaigns.read'
  | 'campaigns.write'
  | 'tenants.read'
  | 'tenants.write'
  | 'settings.read'
  | 'settings.write'
  | 'users.read'
  | 'users.write'
  | 'roles.read'

export const ROLE_PERMISSIONS: Record<RoleId, Permission[]> = {
  platform_admin: ['*'],
  tenant_admin: [
    'dashboard.read',
    'analytics.read',
    'players.read',
    'players.write',
    'transactions.read',
    'deposits.read',
    'withdrawals.read',
    'withdrawals.decide',
    'bonuses.read',
    'bonuses.write',
    'games.read',
    'games.play',
    'games.access.write',
    'affiliates.read',
    'affiliates.write',
    'campaigns.read',
    'campaigns.write',
    'tenants.read',
    'settings.read',
    'settings.write',
    'users.read',
  ],
  risk_analyst: [
    'dashboard.read',
    'analytics.read',
    'players.read',
    'transactions.read',
    'deposits.read',
    'withdrawals.read',
    'withdrawals.decide',
    'games.read',
  ],
  support: [
    'dashboard.read',
    'players.read',
    'bonuses.read',
    'bonuses.write',
    'games.read',
    'games.play',
  ],
}

export const ROLE_LABELS: Record<RoleId, string> = {
  platform_admin: 'Platform Admin',
  tenant_admin: 'Tenant Admin',
  risk_analyst: 'Risk Analyst',
  support: 'Support',
}

export function hasPermission(role: RoleId, permission: Permission): boolean {
  const granted = ROLE_PERMISSIONS[role]
  if (granted.includes('*')) return true
  if (granted.includes(permission)) return true
  const [domain] = permission.split('.')
  return granted.includes(`${domain}.*` as Permission)
}

export function canAccessPath(role: RoleId, path: string): boolean {
  const rules: Array<{ prefix: string; permission: Permission }> = [
    { prefix: '/', permission: 'dashboard.read' },
    { prefix: '/analytics', permission: 'analytics.read' },
    { prefix: '/operations/deposits', permission: 'deposits.read' },
    { prefix: '/operations/withdrawals', permission: 'withdrawals.read' },
    { prefix: '/games/tenant-access', permission: 'games.access.write' },
    { prefix: '/games/lucky-reels', permission: 'games.play' },
    { prefix: '/games/crash', permission: 'games.play' },
    { prefix: '/games', permission: 'games.read' },
    { prefix: '/management/players', permission: 'players.read' },
    { prefix: '/management/tenants', permission: 'tenants.read' },
    { prefix: '/system/settings', permission: 'settings.read' },
  ]

  if (path === '/') return hasPermission(role, 'dashboard.read')

  const match = [...rules]
    .sort((a, b) => b.prefix.length - a.prefix.length)
    .find((rule) => path === rule.prefix || path.startsWith(`${rule.prefix}/`))

  return match ? hasPermission(role, match.permission) : true
}
