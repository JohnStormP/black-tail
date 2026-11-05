import { useEffect, useState } from 'react'
import { Can } from '@/components/auth/Can'
import { Alert, Breadcrumb, Button, Card, Input, Select, useToast } from '@/components/ui'
import { useData } from '@/data/DataStore'
import { useI18n } from '@/i18n/I18nContext'

export function SettingsPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { settings, saveSettings } = useData()
  const [section, setSection] = useState('general')
  const [form, setForm] = useState(settings)
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => setForm(settings), [settings])

  const submit = () => {
    const next: Record<string, string> = {}
    if (!form.workspaceName.trim()) next.workspaceName = t('common.required')
    if (!form.supportEmail.includes('@')) next.supportEmail = t('common.validEmail')
    if (!/^#[0-9A-Fa-f]{6}$/.test(form.accent)) next.accent = t('settings.accentError')
    setErrors(next)
    if (Object.keys(next).length) return
    saveSettings(form)
    toast.push({ title: t('settings.saved'), tone: 'success' })
  }

  const sectionLabel =
    section === 'general'
      ? t('settings.general')
      : section === 'branding'
        ? t('settings.branding')
        : t('settings.security')

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.system') }, { label: t('nav.settings') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('system.settingsTitle')}</h1>
          <p className="bt-page-subtitle">{t('system.settingsSub')}</p>
        </div>
      </div>
      <div className="bt-settings-layout">
        <aside className="bt-panel" style={{ padding: 8 }}>
          <div className="bt-settings-nav" role="tablist" aria-label={t('system.settingsTitle')}>
            {(
              [
                ['general', t('settings.general')],
                ['branding', t('settings.branding')],
                ['security', t('settings.security')],
              ] as const
            ).map(([id, label]) => (
              <button
                key={id}
                type="button"
                role="tab"
                aria-selected={section === id}
                className={section === id ? 'is-active' : ''}
                onClick={() => setSection(id)}
              >
                {label}
              </button>
            ))}
          </div>
        </aside>
        <Card
          title={sectionLabel}
          footer={
            <Can permission="settings.write">
              <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                <Button onClick={submit}>{t('common.save')}</Button>
              </div>
            </Can>
          }
        >
          {section === 'general' && (
            <div className="bt-form-grid">
              <Input
                label={t('settings.workspace')}
                value={form.workspaceName}
                onChange={(e) => setForm({ ...form, workspaceName: e.target.value })}
                error={errors.workspaceName}
              />
              <Select
                label={t('settings.currency')}
                value={form.currency}
                onChange={(e) => setForm({ ...form, currency: e.target.value })}
                options={[
                  { value: 'USD', label: 'USD' },
                  { value: 'EUR', label: 'EUR' },
                ]}
              />
              <Select
                label={t('settings.timezone')}
                value={form.timezone}
                onChange={(e) => setForm({ ...form, timezone: e.target.value })}
                options={[
                  { value: 'UTC', label: 'UTC' },
                  { value: 'Europe/Kyiv', label: 'Europe/Kyiv' },
                ]}
              />
              <Input
                label={t('settings.supportEmail')}
                value={form.supportEmail}
                onChange={(e) => setForm({ ...form, supportEmail: e.target.value })}
                error={errors.supportEmail}
              />
            </div>
          )}
          {section === 'branding' && (
            <div className="bt-form-grid">
              <Input
                label={t('settings.accent')}
                value={form.accent}
                onChange={(e) => setForm({ ...form, accent: e.target.value })}
                error={errors.accent}
              />
              <div
                className="bt-accent-preview"
                style={{ ['--preview' as string]: form.accent }}
                aria-hidden
              />
              <Alert tone="info">{t('settings.accentHint')}</Alert>
            </div>
          )}
          {section === 'security' && (
            <div className="bt-form-grid">
              <Select
                label={t('settings.session')}
                value={form.sessionLength}
                onChange={(e) => setForm({ ...form, sessionLength: e.target.value })}
                options={[
                  { value: '8h', label: '8h' },
                  { value: '24h', label: '24h' },
                ]}
              />
              <Select
                label={t('settings.twoFa')}
                value={form.twoFa}
                onChange={(e) => setForm({ ...form, twoFa: e.target.value })}
                options={[
                  { value: 'required', label: 'required' },
                  { value: 'optional', label: 'optional' },
                ]}
              />
            </div>
          )}
        </Card>
      </div>
    </div>
  )
}
