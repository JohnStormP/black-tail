import { useMemo, useState } from 'react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { Link } from 'react-router-dom'
import { getBetsByDay, getGgrByDay } from '@/api/analyticsApi'
import { listPayments, type PaymentResponse } from '@/api/paymentsApi'
import { listPlayers } from '@/api/playersApi'
import { ChartContainer, chartTheme } from '@/components/charts/ChartContainer'
import { Can } from '@/components/auth/Can'
import {
  AsyncState,
  Badge,
  Button,
  Card,
  Modal,
  Select,
  Status,
  Table,
  type Column,
  useToast,
} from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { useScopedTenant } from '@/hooks/useScopedTenant'
import { useI18n } from '@/i18n/I18nContext'
import {
  chartTooltipStyle,
  inDayRange,
  sumGgr,
  sumPayments,
  toBetsPoints,
  toGgrPoints,
} from '@/lib/analyticsView'
import { downloadCsv } from '@/lib/csv'
import { recentDates } from '@/lib/date-range'
import { errorMessage } from '@/lib/errorMessage'
import { formatDateTime, formatMinor, formatNumber, formatPercent } from '@/lib/format'

const quickGames = [
  { nameKey: 'nav.luckyReels' as const, code: 'lucky_reels', to: '/games/lucky-reels', blurbKey: 'games.luckySub' as const },
  { nameKey: 'nav.crash' as const, code: 'crash', to: '/games/crash', blurbKey: 'games.crashSub' as const },
]

export function DashboardPage() {
  const { t } = useI18n()
  const toast = useToast()
  const { tenantId } = useScopedTenant()
  const [period, setPeriod] = useState('14')
  const [reportOpen, setReportOpen] = useState(false)
  const [includeKpis, setIncludeKpis] = useState(true)
  const [includeSeries, setIncludeSeries] = useState(true)
  const [includeTx, setIncludeTx] = useState(true)
  const range = useMemo(() => recentDates(Number(period)), [period])

  const query = useAsyncQuery(async () => {
    const [ggr, bets, payments, players] = await Promise.all([
      getGgrByDay({ from: range.from, to: range.to, tenantId }),
      getBetsByDay({ from: range.from, to: range.to, tenantId }),
      listPayments(tenantId),
      listPlayers(tenantId),
    ])
    const rangedPayments = payments.filter((payment) => inDayRange(payment.createdAt, range.from, range.to))
    const cash = sumPayments(rangedPayments)
    const ggrTotals = sumGgr(ggr)
    const latest = [...payments].sort((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 6)
    const activePlayers = players.filter((player) => player.status === 'active').length
    const newPlayers = players.filter((player) => inDayRange(player.createdAt, range.from, range.to)).length
    const conversion = players.length === 0 ? 0 : cash.depositors / players.length
    return {
      series: toGgrPoints(ggr),
      bets: toBetsPoints(bets),
      latest,
      payments: rangedPayments,
      ggrMinor: ggrTotals.ggrMinor,
      stakeMinor: ggrTotals.stakeMinor,
      depositsMinor: cash.depositsMinor,
      withdrawalsMinor: cash.withdrawalsMinor,
      activePlayers,
      newPlayers,
      players: players.length,
      conversion,
    }
  }, [tenantId, range.from, range.to])

  const data = query.data

  const txColumns: Column<PaymentResponse>[] = [
    { id: 'id', header: t('col.id'), cell: (row) => <span className="bt-numeric">{row.id}</span> },
    { id: 'player', header: t('col.player'), cell: (row) => <span className="bt-numeric">{row.playerId}</span> },
    { id: 'type', header: t('col.type'), cell: (row) => <Badge tone="neutral">{row.type}</Badge> },
    {
      id: 'amount',
      header: t('col.amount'),
      align: 'right',
      cell: (row) => (
        <span className={row.type === 'withdrawal' ? 'bt-money bt-money--neg' : 'bt-money bt-money--pos'}>
          {row.type === 'withdrawal' ? '-' : '+'}
          {formatMinor(row.amountMinor)}
        </span>
      ),
    },
    { id: 'status', header: t('col.status'), cell: (row) => <Status value={row.status} /> },
    { id: 'createdAt', header: t('col.when'), cell: (row) => formatDateTime(row.createdAt) },
  ]

  const exportTx = () => {
    if (!data) return
    downloadCsv(
      'dashboard-transactions.csv',
      data.latest.map((row) => ({
        id: row.id,
        playerId: row.playerId,
        type: row.type,
        amountMinor: row.amountMinor,
        currency: row.currency,
        status: row.status,
        createdAt: row.createdAt,
      })),
    )
    toast.push({ title: t('common.export'), tone: 'success' })
  }

  const generateReport = () => {
    if (!data) {
      toast.push({ title: errorMessage(query.error), tone: 'danger' })
      return
    }
    const rows: Array<Record<string, string | number>> = []
    if (includeKpis) {
      rows.push(
        { section: 'kpi', metric: 'ggrMinor', value: data.ggrMinor },
        { section: 'kpi', metric: 'stakeMinor', value: data.stakeMinor },
        { section: 'kpi', metric: 'depositsMinor', value: data.depositsMinor },
        { section: 'kpi', metric: 'withdrawalsMinor', value: data.withdrawalsMinor },
        { section: 'kpi', metric: 'activePlayers', value: data.activePlayers },
        { section: 'kpi', metric: 'newPlayers', value: data.newPlayers },
        { section: 'kpi', metric: 'players', value: data.players },
      )
    }
    if (includeSeries) {
      for (const point of data.series) {
        rows.push({
          section: 'series',
          metric: point.date,
          ggr: point.revenue,
          stake: point.stake,
          payout: point.payout,
        })
      }
    }
    if (includeTx) {
      for (const row of data.latest) {
        rows.push({
          section: 'tx',
          metric: row.id,
          amountMinor: row.amountMinor,
          playerId: row.playerId,
          type: row.type,
          status: row.status,
        })
      }
    }
    downloadCsv(`blacktail-report-${range.from}_${range.to}.csv`, rows)
    setReportOpen(false)
    toast.push({ title: t('report.generated'), tone: 'success' })
  }

  const netMinor = (data?.depositsMinor ?? 0) - (data?.withdrawalsMinor ?? 0)

  return (
    <div className="bt-page bt-stack">
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('dashboard.title')}</h1>
          <p className="bt-page-subtitle">{t('dashboard.subtitle')}</p>
        </div>
        <div className="bt-page-actions">
          <Select
            options={[
              { value: '7', label: t('common.last7') },
              { value: '14', label: 'Last 14 days' },
            ]}
            value={period}
            onChange={(event) => setPeriod(event.target.value)}
            aria-label={t('common.period')}
          />
          <Button variant="secondary" onClick={exportTx} disabled={!data}>
            {t('common.export')}
          </Button>
          <Button onClick={() => setReportOpen(true)}>{t('common.newReport')}</Button>
        </div>
      </div>

      <Modal
        open={reportOpen}
        title={t('report.title')}
        onClose={() => setReportOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setReportOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={generateReport}>{t('report.generate')}</Button>
          </>
        }
      >
        <p style={{ marginTop: 0, color: 'var(--bt-text-muted)', fontSize: 13 }}>{t('report.subtitle')}</p>
        <p style={{ fontSize: 12, color: 'var(--bt-text-faint)' }}>
          {range.from} - {range.to}
        </p>
        <div className="bt-stack" style={{ gap: 10 }}>
          <label className="bt-check">
            <input type="checkbox" checked={includeKpis} onChange={(event) => setIncludeKpis(event.target.checked)} />
            {t('report.includeKpis')}
          </label>
          <label className="bt-check">
            <input type="checkbox" checked={includeSeries} onChange={(event) => setIncludeSeries(event.target.checked)} />
            {t('report.includeSeries')}
          </label>
          <label className="bt-check">
            <input type="checkbox" checked={includeTx} onChange={(event) => setIncludeTx(event.target.checked)} />
            {t('report.includeTx')}
          </label>
        </div>
      </Modal>

      <Can permission="games.play">
        <Card
          title={t('common.games')}
          action={
            <Link to="/games" className="bt-btn bt-btn--ghost bt-btn--sm">
              {t('common.catalog')}
            </Link>
          }
        >
          <div className="bt-grid-4">
            {quickGames.map((game) => (
              <Link key={game.code} to={game.to} className="bt-game-card" style={{ minHeight: 0 }}>
                <h3 className="bt-game-card__name">{t(game.nameKey)}</h3>
                <p style={{ margin: 0, color: 'var(--bt-text-faint)', fontSize: 12 }}>{t(game.blurbKey)}</p>
              </Link>
            ))}
          </div>
        </Card>
      </Can>

      <AsyncState status={query.status} onRetry={query.reload} skeletonRows={6}>
        {data ? (
          <div className="bt-stack">
            <div className="bt-kpi-strip">
              <div className="bt-stat">
                <p className="bt-stat__label">{t('dashboard.totalBalance')}</p>
                <p className="bt-stat__value bt-numeric">{formatMinor(netMinor)}</p>
                <div className="bt-stat__meta">
                  <span>
                    {range.from} - {range.to}
                  </span>
                </div>
              </div>
              <div className="bt-stat">
                <p className="bt-stat__label">{t('dashboard.netRevenue')}</p>
                <p className="bt-stat__value bt-numeric">{formatMinor(data.ggrMinor)}</p>
                <div className="bt-stat__meta">
                  <span>GGR</span>
                </div>
              </div>
              <div className="bt-stat">
                <p className="bt-stat__label">{t('dashboard.activePlayers')}</p>
                <p className="bt-stat__value bt-numeric">{formatNumber(data.activePlayers)}</p>
                <div className="bt-stat__meta">
                  <span>
                    {formatNumber(data.players)} {t('nav.players')}
                  </span>
                </div>
              </div>
              <div className="bt-stat">
                <p className="bt-stat__label">{t('dashboard.conversion')}</p>
                <p className="bt-stat__value bt-numeric">{formatPercent(data.conversion)}</p>
                <div className="bt-stat__meta">
                  <span>{t('dashboard.ftd')}</span>
                </div>
              </div>
            </div>

            <div className="bt-dash-main">
              <Card title={t('dashboard.revenue')} action={<Badge tone="accent">USD</Badge>} footer={t('dashboard.revFooter')}>
                <ChartContainer>
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data.series}>
                      <defs>
                        <linearGradient id="revFill" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stopColor={chartTheme.series[0]} stopOpacity={0.35} />
                          <stop offset="100%" stopColor={chartTheme.series[0]} stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                      <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <YAxis stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <RechartsTooltip contentStyle={chartTooltipStyle} />
                      <Area type="monotone" dataKey="revenue" stroke={chartTheme.series[0]} fill="url(#revFill)" strokeWidth={2} />
                      <Area type="monotone" dataKey="stake" stroke={chartTheme.series[1]} fill="transparent" strokeWidth={1.5} strokeDasharray="4 4" />
                    </AreaChart>
                  </ResponsiveContainer>
                </ChartContainer>
              </Card>

              <Card title={t('dashboard.cashMovement')}>
                <div className="bt-metric-list">
                  <div className="bt-metric-row">
                    <span>GGR</span>
                    <strong className="bt-numeric">{formatMinor(data.ggrMinor)}</strong>
                  </div>
                  <div className="bt-metric-row">
                    <span>{t('dashboard.deposits')}</span>
                    <strong className="bt-numeric" style={{ color: 'var(--bt-success)' }}>
                      {formatMinor(data.depositsMinor)}
                    </strong>
                  </div>
                  <div className="bt-metric-row">
                    <span>{t('dashboard.withdrawals')}</span>
                    <strong className="bt-numeric" style={{ color: 'var(--bt-danger)' }}>
                      {formatMinor(data.withdrawalsMinor)}
                    </strong>
                  </div>
                  <div className="bt-metric-row">
                    <span>{t('dashboard.newPlayers')}</span>
                    <strong className="bt-numeric">{formatNumber(data.newPlayers)}</strong>
                  </div>
                </div>
                <div style={{ marginTop: 20 }}>
                  <ChartContainer size="sm">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={data.bets}>
                        <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                        <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                        <YAxis hide />
                        <RechartsTooltip contentStyle={chartTooltipStyle} />
                        <Bar dataKey="bets" fill={chartTheme.series[0]} radius={[4, 4, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </ChartContainer>
                </div>
              </Card>
            </div>

            <Card
              title={t('dashboard.latestTx')}
              action={
                <Link to="/analytics/transactions" className="bt-btn bt-btn--ghost bt-btn--sm">
                  {t('common.viewAll')}
                </Link>
              }
            >
              <Table columns={txColumns} rows={data.latest} />
            </Card>
          </div>
        ) : null}
      </AsyncState>
    </div>
  )
}
