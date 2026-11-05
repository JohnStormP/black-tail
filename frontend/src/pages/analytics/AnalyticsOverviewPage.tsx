import { useMemo, useState } from 'react'
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip as RechartsTooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { getBetsByDay, getGgrByDay } from '@/api/analyticsApi'
import { ChartContainer, chartTheme } from '@/components/charts/ChartContainer'
import { AsyncState, Breadcrumb, Card, Select, Stat } from '@/components/ui'
import { useAsyncQuery } from '@/hooks/useAsyncQuery'
import { TenantSelect, useScopedTenant } from '@/hooks/useScopedTenant'
import { useI18n } from '@/i18n/I18nContext'
import { chartTooltipStyle, sumBets, sumGgr, toBetsPoints, toGgrPoints } from '@/lib/analyticsView'
import { recentDates } from '@/lib/date-range'
import { formatMinor, formatNumber } from '@/lib/format'

export function AnalyticsOverviewPage() {
  const { t } = useI18n()
  const { tenantId, setTenantId, tenants } = useScopedTenant()
  const [period, setPeriod] = useState('14')
  const range = useMemo(() => recentDates(Number(period)), [period])
  const query = useAsyncQuery(async () => {
    const [ggr, bets] = await Promise.all([
      getGgrByDay({ from: range.from, to: range.to, tenantId }),
      getBetsByDay({ from: range.from, to: range.to, tenantId }),
    ])
    return {
      ggr: toGgrPoints(ggr),
      bets: toBetsPoints(bets),
      ggrTotals: sumGgr(ggr),
      betsTotals: sumBets(bets),
    }
  }, [tenantId, range.from, range.to])

  const data = query.data

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb
        items={[
          { label: t('group.analytics'), to: '/analytics' },
          { label: t('nav.overview') },
        ]}
      />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('analytics.overviewTitle')}</h1>
          <p className="bt-page-subtitle">{t('analytics.overviewSub')}</p>
        </div>
        <div className="bt-page-actions">
          <TenantSelect tenantId={tenantId} tenants={tenants} onChange={setTenantId} label={t('col.tenant')} />
          <Select
            options={[
              { value: '7', label: t('common.last7') },
              { value: '14', label: 'Last 14 days' },
            ]}
            value={period}
            onChange={(event) => setPeriod(event.target.value)}
            aria-label={t('common.period')}
          />
        </div>
      </div>
      <AsyncState status={query.status} onRetry={query.reload} skeletonRows={6}>
        {data ? (
          <div className="bt-stack">
            <div className="bt-grid-4">
              <Stat label="GGR" value={formatMinor(data.ggrTotals.ggrMinor)} hint={`${range.from} - ${range.to}`} />
              <Stat label="Stake" value={formatMinor(data.ggrTotals.stakeMinor)} />
              <Stat label="Payout" value={formatMinor(data.ggrTotals.payoutMinor)} />
              <Stat label={t('common.bet')} value={formatNumber(data.betsTotals.bets)} />
            </div>
            <div className="bt-grid-2">
              <Card title="GGR">
                <ChartContainer>
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data.ggr}>
                      <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                      <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <YAxis stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <RechartsTooltip contentStyle={chartTooltipStyle} />
                      <Area
                        type="monotone"
                        dataKey="revenue"
                        stroke={chartTheme.series[0]}
                        fill={chartTheme.series[0]}
                        fillOpacity={0.15}
                        strokeWidth={2}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                </ChartContainer>
              </Card>
              <Card title={t('common.bet')}>
                <ChartContainer>
                  <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data.bets}>
                      <CartesianGrid stroke={chartTheme.grid} vertical={false} />
                      <XAxis dataKey="day" stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <YAxis stroke={chartTheme.axis} fontSize={11} tickLine={false} axisLine={false} />
                      <RechartsTooltip contentStyle={chartTooltipStyle} />
                      <Area
                        type="monotone"
                        dataKey="bets"
                        stroke={chartTheme.series[1]}
                        fill={chartTheme.series[1]}
                        fillOpacity={0.12}
                        strokeWidth={2}
                      />
                      <Area
                        type="monotone"
                        dataKey="stake"
                        stroke={chartTheme.series[3]}
                        fill="transparent"
                        strokeWidth={1.5}
                      />
                    </AreaChart>
                  </ResponsiveContainer>
                </ChartContainer>
              </Card>
            </div>
          </div>
        ) : null}
      </AsyncState>
    </div>
  )
}
