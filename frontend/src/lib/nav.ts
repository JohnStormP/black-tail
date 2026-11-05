import {
  ArrowDownToLine,
  ArrowUpFromLine,
  Building2,
  ChartColumn,
  CircleDollarSign,
  Dices,
  Gamepad2,
  LayoutDashboard,
  Receipt,
  Rocket,
  Settings,
  ToggleLeft,
  Users,
  type LucideIcon,
} from 'lucide-react'
import type { Permission } from '@/auth/permissions'
import type { TranslationKey } from '@/i18n/translations'

export type NavItem = {
  labelKey: TranslationKey
  to: string
  icon: LucideIcon
  permission: Permission
}

export type NavGroup = {
  labelKey: TranslationKey
  items: NavItem[]
}

export const navGroups: NavGroup[] = [
  {
    labelKey: 'group.overview',
    items: [{ labelKey: 'nav.dashboard', to: '/', icon: LayoutDashboard, permission: 'dashboard.read' }],
  },
  {
    labelKey: 'group.analytics',
    items: [
      { labelKey: 'nav.overview', to: '/analytics', icon: ChartColumn, permission: 'analytics.read' },
      { labelKey: 'nav.revenue', to: '/analytics/revenue', icon: CircleDollarSign, permission: 'analytics.read' },
      { labelKey: 'nav.players', to: '/analytics/players', icon: Users, permission: 'analytics.read' },
      { labelKey: 'nav.transactions', to: '/analytics/transactions', icon: Receipt, permission: 'transactions.read' },
    ],
  },
  {
    labelKey: 'group.operations',
    items: [
      { labelKey: 'nav.deposits', to: '/operations/deposits', icon: ArrowDownToLine, permission: 'deposits.read' },
      { labelKey: 'nav.withdrawals', to: '/operations/withdrawals', icon: ArrowUpFromLine, permission: 'withdrawals.read' },
    ],
  },
  {
    labelKey: 'group.games',
    items: [
      { labelKey: 'nav.catalog', to: '/games', icon: Gamepad2, permission: 'games.read' },
      { labelKey: 'nav.luckyReels', to: '/games/lucky-reels', icon: Dices, permission: 'games.play' },
      { labelKey: 'nav.crash', to: '/games/crash', icon: Rocket, permission: 'games.play' },
      { labelKey: 'nav.tenantAccess', to: '/games/tenant-access', icon: ToggleLeft, permission: 'games.access.write' },
    ],
  },
  {
    labelKey: 'group.management',
    items: [
      { labelKey: 'nav.players', to: '/management/players', icon: Users, permission: 'players.read' },
      { labelKey: 'nav.tenants', to: '/management/tenants', icon: Building2, permission: 'tenants.read' },
    ],
  },
  {
    labelKey: 'group.system',
    items: [
      { labelKey: 'nav.settings', to: '/system/settings', icon: Settings, permission: 'settings.read' },
    ],
  },
]
