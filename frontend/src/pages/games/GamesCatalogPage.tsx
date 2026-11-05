import { useState } from 'react'
import { Link } from 'react-router-dom'
import { listGames, registerGame, type GameResponse } from '@/api/gamesApi'
import { ApiError } from '@/api/http'
import { useAuth } from '@/auth/AuthContext'
import { Can } from '@/components/auth/Can'
import {
  AsyncState,
  Breadcrumb,
  Button,
  Input,
  Modal,
  Status,
  useToast,
} from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useI18n } from '@/i18n/I18nContext'
import { downloadCsv } from '@/lib/csv'
import { CRASH_GAME_ID, LUCKY_REELS_GAME_ID } from '@/lib/demoIds'
import { formatDateTime } from '@/lib/format'
import { newUlid } from '@/lib/ulid'

const playRoutesByCode: Record<string, string> = {
  lucky_reels: '/games/lucky-reels',
  crash: '/games/crash',
}

const playRoutesById: Record<string, string> = {
  [LUCKY_REELS_GAME_ID]: '/games/lucky-reels',
  [CRASH_GAME_ID]: '/games/crash',
}

function apiErrorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message
  if (err instanceof Error && err.message) return err.message
  return 'Request failed'
}

function playPath(game: GameResponse): string | undefined {
  return playRoutesByCode[game.code] ?? playRoutesById[game.id]
}

export function GamesCatalogPage() {
  const toast = useToast()
  const { t } = useI18n()
  const { can } = useAuth()
  const query = useAsyncQuery(() => listGames(), [])
  const [open, setOpen] = useState(false)
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const games = query.data ?? []

  const exportCsv = () => {
    downloadCsv(
      'games-catalog.csv',
      games.map((game) => ({
        id: game.id,
        code: game.code,
        name: game.name,
        status: game.status,
        createdAt: game.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  const submit = async () => {
    const next: Record<string, string> = {}
    const normalized = code.trim().toLowerCase()
    if (!/^[a-z][a-z0-9_]{1,63}$/.test(normalized)) next.code = 'Lowercase snake_case, 2-64 chars'
    if (!name.trim()) next.name = t('common.required')
    setErrors(next)
    if (Object.keys(next).length) return
    setSaving(true)
    try {
      await registerGame({
        gameId: newUlid(),
        code: normalized,
        name: name.trim(),
      })
      toast.push({ title: 'Game registered', tone: 'success' })
      setOpen(false)
      setCode('')
      setName('')
      await query.reload()
    } catch (err) {
      toast.push({ title: 'Game registration failed', description: apiErrorMessage(err), tone: 'danger' })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.games') }, { label: t('nav.catalog') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('games.catalogTitle')}</h1>
          <p className="bt-page-subtitle">{t('games.catalogSub')}</p>
        </div>
        <div className="bt-page-actions">
          <Button variant="secondary" onClick={exportCsv}>
            {t('common.export')}
          </Button>
          <Can permission="games.write">
            <Button variant="secondary" onClick={() => setOpen(true)}>
              {t('form.registerGame')}
            </Button>
          </Can>
        </div>
      </div>
      <AsyncState status={query.status} onRetry={query.reload} skeletonRows={2}>
        <div className="bt-grid-2">
          {games.map((game) => {
            const href = playPath(game)
            return (
              <article key={game.id} className="bt-game-card">
                <div className="bt-game-card__top">
                  <div>
                    <div className="bt-game-card__code">{game.code}</div>
                    <h3 className="bt-game-card__name">{game.name}</h3>
                  </div>
                  <Status value={game.status} />
                </div>
                <div className="bt-metric-list">
                  <div className="bt-metric-row">
                    <span>Id</span>
                    <strong className="bt-numeric">{game.id}</strong>
                  </div>
                  <div className="bt-metric-row">
                    <span>Created</span>
                    <strong>{formatDateTime(game.createdAt)}</strong>
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {href && game.status === 'active' && can('games.play') ? (
                    <Link to={href} className="bt-btn bt-btn--primary">
                      Open {game.name}
                    </Link>
                  ) : (
                    <Button variant="secondary" disabled>
                      Unavailable
                    </Button>
                  )}
                  <Can permission="games.access.write">
                    <Link to="/games/tenant-access" className="bt-btn bt-btn--secondary">
                      Manage access
                    </Link>
                  </Can>
                </div>
              </article>
            )
          })}
        </div>
      </AsyncState>
      <Modal
        open={open}
        title={t('form.registerGame')}
        onClose={() => setOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setOpen(false)} disabled={saving}>
              {t('common.cancel')}
            </Button>
            <Button loading={saving} onClick={() => void submit()}>
              {t('common.create')}
            </Button>
          </>
        }
      >
        <div className="bt-form-grid">
          <Input label="Code" value={code} onChange={(e) => setCode(e.target.value)} error={errors.code} hint="e.g. mega_wheel" />
          <Input label={t('col.name')} value={name} onChange={(e) => setName(e.target.value)} error={errors.name} />
        </div>
      </Modal>
    </div>
  )
}
