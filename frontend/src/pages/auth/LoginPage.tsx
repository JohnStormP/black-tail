import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/auth/AuthContext'
import { Alert, Button, Input } from '@/components/ui'
import { useI18n } from '@/i18n/I18nContext'
import { ADMIN_EMAIL, PASSWORD, SUPER_EMAIL } from '@/lib/demoIds'

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { login, user } = useAuth()
  const { t } = useI18n()
  const [email, setEmail] = useState(ADMIN_EMAIL)
  const [password, setPassword] = useState(PASSWORD)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string }>({})

  if (user) {
    const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'
    return <Navigate to={redirectTo} replace />
  }

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault()
    const nextErrors: typeof fieldErrors = {}
    if (!email.trim()) nextErrors.email = t('auth.required')
    if (!password) nextErrors.password = t('auth.required')
    setFieldErrors(nextErrors)
    setError('')
    if (Object.keys(nextErrors).length > 0) return

    setLoading(true)
    try {
      await login(email, password)
      const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'
      navigate(redirectTo, { replace: true })
    } catch {
      setError(t('auth.invalid'))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="bt-auth">
      <div className="bt-auth__card">
        <div className="bt-auth__brand">
          <div className="bt-sidebar__mark" aria-hidden>
            BT
          </div>
          <strong>{t('app.name')}</strong>
        </div>
        <h1 className="bt-auth__title">{t('auth.signIn')}</h1>
        <p className="bt-auth__subtitle">{t('auth.subtitle')}</p>
        {error ? (
          <Alert tone="danger" title={t('auth.invalid')}>
            Use a real backend user (session cookie via Vite /api proxy).
          </Alert>
        ) : (
          <Alert tone="info">
            Demo accounts: {ADMIN_EMAIL} (admin) and {SUPER_EMAIL} (super). Password: {PASSWORD}.
          </Alert>
        )}
        <form className="bt-auth__form" onSubmit={onSubmit} noValidate>
          <Input
            label={t('auth.email')}
            type="email"
            name="email"
            autoComplete="username"
            value={email}
            error={fieldErrors.email}
            onChange={(event) => setEmail(event.target.value)}
          />
          <Input
            label={t('auth.password')}
            type="password"
            name="password"
            autoComplete="current-password"
            value={password}
            error={fieldErrors.password}
            onChange={(event) => setPassword(event.target.value)}
          />
          <Button type="submit" loading={loading} style={{ width: '100%' }}>
            {t('auth.continue')}
          </Button>
        </form>
      </div>
    </div>
  )
}
