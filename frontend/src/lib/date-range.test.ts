import { describe, expect, it } from 'vitest'
import { filterByDateRange, lastNDates, recentDates, sumSeries } from './date-range'

describe('date-range', () => {
  it('filters inclusive date bounds', () => {
    const rows = [
      { date: '2026-09-01', revenue: 1 },
      { date: '2026-09-05', revenue: 2 },
      { date: '2026-10-04', revenue: 3 },
    ]
    expect(filterByDateRange(rows, '2026-09-05', '2026-10-04')).toHaveLength(2)
  })

  it('computes last N dates from anchor', () => {
    expect(lastNDates('2026-10-04', 7)).toEqual({ from: '2026-09-28', to: '2026-10-04' })
  })

  it('builds a recent inclusive range in UTC', () => {
    expect(recentDates(7, new Date('2026-11-05T15:00:00Z'))).toEqual({
      from: '2026-10-30',
      to: '2026-11-05',
    })
  })

  it('sums series fields', () => {
    expect(
      sumSeries([
        { revenue: 10, deposits: 2, withdrawals: 1, active: 5, new: 1 },
        { revenue: 5, deposits: 3, withdrawals: 2, active: 7, new: 2 },
      ]),
    ).toEqual({ revenue: 15, deposits: 5, withdrawals: 3, active: 12, newPlayers: 3 })
  })
})
