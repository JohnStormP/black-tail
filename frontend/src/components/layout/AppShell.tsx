import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { ROLE_LABELS, useAuth } from '@/auth/AuthContext'
import { useI18n } from '@/i18n/I18nContext'
import { Header } from './Header'
import { Sidebar } from './Sidebar'

export function AppShell() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const { user } = useAuth()
  const { t } = useI18n()

  return (
    <div className="bt-shell">
      <a href="#main-content" className="bt-skip-link">
        {t('common.skip')}
      </a>
      {mobileOpen ? (
        <div className="bt-sidebar-backdrop" onClick={() => setMobileOpen(false)} aria-hidden />
      ) : null}
      <Sidebar open={mobileOpen} onNavigate={() => setMobileOpen(false)} />
      <div className="bt-shell__main">
        <Header onMenu={() => setMobileOpen(true)} />
        {user ? (
          <div className="bt-role-banner" role="status">
            {t('common.viewingAs', {
              role: ROLE_LABELS[user.role],
              tenant: user.tenant,
            })}
          </div>
        ) : null}
        <main id="main-content" tabIndex={-1}>
          <Outlet />
        </main>
      </div>
    </div>
  )
}
