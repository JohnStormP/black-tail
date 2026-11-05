import { describe, expect, it } from 'vitest'
import { canAccessPath, hasPermission } from './permissions'

describe('permissions', () => {
  it('platform_admin has wildcard', () => {
    expect(hasPermission('platform_admin', 'players.write')).toBe(true)
    expect(hasPermission('platform_admin', 'roles.read')).toBe(true)
  })

  it('support cannot decide withdrawals', () => {
    expect(hasPermission('support', 'withdrawals.decide')).toBe(false)
    expect(hasPermission('support', 'games.play')).toBe(true)
  })

  it('risk_analyst can open analytics but not settings write path', () => {
    expect(canAccessPath('risk_analyst', '/analytics')).toBe(true)
    expect(canAccessPath('risk_analyst', '/system/settings')).toBe(false)
  })

  it('tenant_admin can access players and games', () => {
    expect(canAccessPath('tenant_admin', '/management/players')).toBe(true)
    expect(canAccessPath('tenant_admin', '/games/lucky-reels')).toBe(true)
  })
})
