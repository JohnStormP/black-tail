import { Bell, Menu } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuth, ROLE_LABELS } from '@/auth/AuthContext'
import { Dropdown, IconButton, Select } from '@/components/ui'
import { useData } from '@/data/DataStore'
import { useI18n } from '@/i18n/I18nContext'
import { GlobalSearch } from './GlobalSearch'

export function Header({ onMenu }: { onMenu: () => void }) {
  const navigate = useNavigate()
  const { user, logout } = useAuth()
  const { t, locale, setLocale } = useI18n()
  const {
    notifications,
    unreadCount,
    markNotificationRead,
    markAllNotificationsRead,
    clearReadNotifications,
  } = useData()

  const initials = (user?.name ?? 'U')
    .split(' ')
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')

  return (
    <header className="bt-header">
      <div className="bt-header__left">
        <IconButton label={t('common.openNav')} className="bt-mobile-nav-btn" onClick={onMenu}>
          <Menu size={18} />
        </IconButton>
        <div className="bt-header__search">
          <GlobalSearch />
        </div>
      </div>
      <div className="bt-header__right">
        <div className="bt-header__lang">
          <Select
            aria-label={t('common.language')}
            options={[
              { value: 'en', label: 'EN' },
              { value: 'ua', label: 'UA' },
            ]}
            value={locale}
            onChange={(event) => setLocale(event.target.value as 'en' | 'ua')}
          />
        </div>
        {user ? (
          <div className="bt-header__role" aria-label={t('common.roleDemo')}>
            <span className="bt-profile__role">{ROLE_LABELS[user.role]}</span>
          </div>
        ) : null}
        <Dropdown
          trigger={
            <span className="bt-notif-trigger">
              <IconButton label={t('common.notifications')}>
                <Bell size={16} />
              </IconButton>
              {unreadCount > 0 ? <span className="bt-notif-badge" aria-hidden>{unreadCount}</span> : null}
            </span>
          }
          items={[
            ...(notifications.length === 0
              ? [{ id: 'empty', label: t('common.noNotifications') }]
              : notifications.map((item) => ({
                  id: item.id,
                  label: `${item.unread ? '● ' : ''}${item.title} · ${item.time}`,
                  onSelect: () => markNotificationRead(item.id),
                }))),
            { id: 'sep', label: '', separator: true },
            {
              id: 'read-all',
              label: t('common.markAllRead'),
              onSelect: markAllNotificationsRead,
            },
            {
              id: 'clear',
              label: t('common.clearRead'),
              onSelect: clearReadNotifications,
            },
          ]}
        />
        <Dropdown
          trigger={
            <button type="button" className="bt-profile" aria-label={t('common.accountMenu')}>
              <span className="bt-avatar">{initials}</span>
              <span className="bt-profile__meta">
                <span className="bt-profile__name">{user?.name ?? 'User'}</span>
                <span className="bt-profile__role">
                  {user ? ROLE_LABELS[user.role] : ''}
                </span>
              </span>
            </button>
          }
          items={[
            {
              id: 'settings',
              label: t('nav.settings'),
              onSelect: () => navigate('/system/settings'),
            },
            { id: 'sep', label: '', separator: true },
            {
              id: 'logout',
              label: t('auth.signOut'),
              danger: true,
              onSelect: () => {
                logout()
                navigate('/login')
              },
            },
          ]}
        />
      </div>
    </header>
  )
}
