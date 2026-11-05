import { NavLink } from 'react-router-dom'
import { useAuth } from '@/auth/AuthContext'
import { useI18n } from '@/i18n/I18nContext'
import { navGroups } from '@/lib/nav'
import { cn } from '@/lib/cn'

export function Sidebar({
  open,
  onNavigate,
}: {
  open: boolean
  onNavigate?: () => void
}) {
  const { can, user } = useAuth()
  const { t } = useI18n()

  return (
    <aside className={cn('bt-sidebar', open && 'bt-sidebar--open')} aria-label="Application">
      <div className="bt-sidebar__brand">
        <div className="bt-sidebar__mark" aria-hidden>
          BT
        </div>
        <div>
          <div className="bt-sidebar__name">{t('app.name')}</div>
          <div style={{ fontSize: 10, color: 'var(--bt-text-faint)' }}>{t('app.tagline')}</div>
        </div>
      </div>
      <nav className="bt-sidebar__nav" aria-label="Primary">
        {navGroups.map((group) => {
          const items = group.items.filter((item) => can(item.permission))
          if (items.length === 0) return null
          return (
            <div key={group.labelKey} className="bt-nav-group">
              <div className="bt-nav-group__label">{t(group.labelKey)}</div>
              {items.map((item) => {
                const Icon = item.icon
                return (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end
                    className={({ isActive }) => cn('bt-nav-link', isActive && 'bt-nav-link--active')}
                    onClick={onNavigate}
                  >
                    <Icon aria-hidden />
                    <span>{t(item.labelKey)}</span>
                  </NavLink>
                )
              })}
            </div>
          )
        })}
      </nav>
      <div className="bt-sidebar__footer">
        <div style={{ fontSize: 11, color: 'var(--bt-text-faint)', padding: '0 8px' }}>
          Workspace · {user?.tenant ?? '-'}
        </div>
      </div>
    </aside>
  )
}
