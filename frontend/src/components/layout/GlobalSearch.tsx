import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Search } from '@/components/ui'
import { useData } from '@/data/DataStore'
import { useI18n } from '@/i18n/I18nContext'

type Hit = {
  id: string
  group: string
  label: string
  to: string
}

export function GlobalSearch() {
  const { t } = useI18n()
  const navigate = useNavigate()
  const { players, transactions, games, tenants } = useData()
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(0)
  const rootRef = useRef<HTMLDivElement>(null)

  const hits = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (q.length < 2) return [] as Hit[]
    const next: Hit[] = []

    const tenantName = (tenantId: string) => tenants.find((item) => item.id === tenantId)?.name ?? tenantId

    for (const player of players) {
      const name = tenantName(player.tenantId)
      if (player.email.toLowerCase().includes(q) || name.toLowerCase().includes(q)) {
        next.push({
          id: `p-${player.id}`,
          group: t('search.players'),
          label: `${player.email} · ${name}`,
          to: `/management/players/${player.id}`,
        })
      }
    }
    for (const tx of transactions) {
      if (tx.id.toLowerCase().includes(q) || tx.player.toLowerCase().includes(q)) {
        next.push({
          id: `t-${tx.id}`,
          group: t('search.transactions'),
          label: `${tx.id} · ${tx.type} · ${tx.player}`,
          to: tx.type === 'withdrawal' ? '/operations/withdrawals' : '/operations/deposits',
        })
      }
    }
    for (const game of games) {
      if (game.name.toLowerCase().includes(q) || game.code.toLowerCase().includes(q)) {
        const route =
          game.code === 'lucky_reels'
            ? '/games/lucky-reels'
            : game.code === 'crash'
              ? '/games/crash'
              : '/games'
        next.push({
          id: `g-${game.id}`,
          group: t('search.games'),
          label: `${game.name} · ${game.code}`,
          to: route,
        })
      }
    }
    for (const tenant of tenants) {
      if (tenant.name.toLowerCase().includes(q)) {
        next.push({
          id: `n-${tenant.id}`,
          group: t('search.tenants'),
          label: tenant.name,
          to: '/management/tenants',
        })
      }
    }
    return next.slice(0, 12)
  }, [query, players, transactions, games, tenants, t])

  useEffect(() => {
    setActive(0)
  }, [query])

  useEffect(() => {
    const onDoc = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }
    window.addEventListener('mousedown', onDoc)
    return () => window.removeEventListener('mousedown', onDoc)
  }, [])

  const go = (hit: Hit) => {
    navigate(hit.to)
    setOpen(false)
    setQuery('')
  }

  return (
    <div className="bt-global-search" ref={rootRef}>
      <Search
        placeholder={t('common.search')}
        aria-label={t('common.search')}
        aria-expanded={open && query.trim().length >= 2}
        aria-controls="bt-global-search-results"
        role="combobox"
        autoComplete="off"
        value={query}
        onChange={(event) => {
          setQuery(event.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={(event) => {
          if (!open) return
          if (event.key === 'ArrowDown') {
            event.preventDefault()
            setActive((i) => Math.min(i + 1, Math.max(hits.length - 1, 0)))
          } else if (event.key === 'ArrowUp') {
            event.preventDefault()
            setActive((i) => Math.max(i - 1, 0))
          } else if (event.key === 'Enter' && hits[active]) {
            event.preventDefault()
            go(hits[active])
          } else if (event.key === 'Escape') {
            setOpen(false)
          }
        }}
      />
      {open && query.trim().length >= 2 ? (
        <div id="bt-global-search-results" className="bt-global-search__panel" role="listbox">
          {hits.length === 0 ? (
            <div className="bt-global-search__empty">{t('search.noResults')}</div>
          ) : (
            hits.map((hit, index) => (
              <button
                key={hit.id}
                type="button"
                role="option"
                aria-selected={index === active}
                className={`bt-global-search__item ${index === active ? 'is-active' : ''}`}
                onMouseEnter={() => setActive(index)}
                onClick={() => go(hit)}
              >
                <span className="bt-global-search__group">{hit.group}</span>
                <span>{hit.label}</span>
              </button>
            ))
          )}
          <div className="bt-global-search__hint">{t('search.hint')}</div>
        </div>
      ) : null}
    </div>
  )
}
