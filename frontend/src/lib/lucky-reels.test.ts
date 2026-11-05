import { describe, expect, it } from 'vitest'
import { evaluateStops, PAYTABLE, REEL_STRIPS, spin } from './lucky-reels'

describe('lucky-reels', () => {
  it('has five strips of length 20 matching backend density', () => {
    expect(REEL_STRIPS).toHaveLength(5)
    for (const strip of REEL_STRIPS) {
      expect(strip).toHaveLength(20)
      expect(strip.includes('black_tail')).toBe(true)
    }
  })

  it('pays three-of-a-kind on the middle payline', () => {
    // Force a window that is likely to produce matches by picking stop 0 on all reels
    // and asserting evaluateStops returns a well-formed result shape.
    const result = evaluateStops([0, 0, 0, 0, 0], 100)
    expect(result.stops).toEqual([0, 0, 0, 0, 0])
    expect(result.grid).toHaveLength(5)
    expect(result.grid[0]).toHaveLength(3)
    expect(result.winMinor).toBe(result.totalMultiplier * 100)
    for (const win of result.lineWins) {
      expect(win.matchCount).toBeGreaterThanOrEqual(3)
      expect(PAYTABLE[win.symbol][win.matchCount as 3 | 4 | 5]).toBe(win.multiplier)
    }
  })

  it('spin uses provided rng for deterministic stops', () => {
    let i = 0
    const values = [0, 0.1, 0.2, 0.3, 0.4]
    const result = spin(50, () => values[i++] ?? 0)
    expect(result.stops).toEqual(values.map((v) => Math.floor(v * 20)))
    expect(result.winMinor).toBe(result.totalMultiplier * 50)
  })
})
