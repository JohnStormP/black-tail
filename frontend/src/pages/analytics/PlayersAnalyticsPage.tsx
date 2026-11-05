import { useState } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { getBetsByDay } from '@/api/analyticsApi'
import { listPlayers, type PlayerResponse } from '@/api/playersApi'
import { ChartContainer, chartTheme } from '@/components/charts/ChartContainer'
import { AsyncState, Breadcrumb, Card, DatePicker, Stat, Status, Table, type Column } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { TenantSelect, useScopedTenant } from '@/hooks/useScopedTenant'
import { useI18n } from '@/i18n/I18nContext'
import { chartTooltipStyle, sumBets, toBetsPoints } from '@/lib/analyticsView'
import { recentDates } from '@/lib/date-range'
import { formatDateTime, formatMinor, formatNumber } from '@/lib/format'

export function PlayersAnalyticsPage() {
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const [from, setFrom] = useState(() => recentDates(14).from)
  const [to, setTo] = useState(() => recentDates(14).to)
  const query = useAsyncQuery(async () => {
    const [players, bets] = await Promise.all([
      listPlayers(tenantId),
      getBetsByDay({ from, to, tenantId }),
    ])
    const totals = sumBets(bets)
    const rows = [...players].sort((a, b) => b.createdAt.localeCompare(a.createdAt))
    return { players: rows, series: toBetsPoints(bets), totals, active: rows.filter((player) => player.status === 'active').length }
  }, [tenantId, from, to])

  const columns: Column<PlayerResponse>[] = [
    { id: 'email', header: t('col.player'), cell: (row) => row.email },
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
  ]

  const data = query.data

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb
        items={[
          { label: t('group.analytics'), to: '/analytics' },
          { label: t('nav.players') },
        ]}
      />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('analytics.playersTitle')}</h1>
          <p className="bt-page-subtitle">{t('analytics.playersSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <DatePicker
            label=""
            aria-label={t('analytics.from')}
            value={from}
            onChange={(event) => setFrom(event.target.value)}
          />
          <DatePicker
            label=""
            aria-label={t('analytics.to')}
            value={to}
            onChange={(event) => setTo(event.target.value)}
          />
        </div>
      </div>
      <AsyncState status={query.status} onRetry={query.reload} skeletonRows={6}>
        {data ? (
          <div className="bt-stack">
            <div className="bt-grid-3">
              <Stat label={t('nav.players')} value={formatNumber(data.players.length)} />
              <Stat label={t('dashboard.activePlayers')} value={formatNumber(data.active)} />
              <Stat label={t('common.bet')} value={formatNumber(data.totals.bets)} hint={formatMinor(data.totals.amountMinor)} />
            </div>
            <Card title={t('common.bet')}>
              <ChartContainer>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.series}>
                    <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                    <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                    <YAxis stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                    <RechartsTooltip contentStyle={chartTooltipStyle} />
                    <Bar dataKey="bets" name="Bets" fill={chartTheme.series[0]} radius={[3, 3, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </ChartContainer>
            </Card>
            <Card title={t('nav.players')}>
              <Table columns={columns} rows={data.players} />
            </Card>
          </div>
        ) : null}
      </AsyncState>
    </div>
  )
}
