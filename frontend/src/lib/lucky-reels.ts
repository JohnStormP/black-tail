/** Mirrors App\Game\Domain\ValueObject\ReelSymbol + LuckyReelsConfig::default() */

export type ReelSymbolId =
  | 'siamese'
  | 'persian'
  | 'maine'
  | 'bengal'
  | 'sphynx'
  | 'black_tail'

export const REELS = 5
export const ROWS = 3

export const SYMBOL_META: Record<
  ReelSymbolId,
  { label: string; short: string; tier: 'low' | 'mid' | 'high' | 'wild' }
> = {
  siamese: { label: 'Siamese', short: 'Sia', tier: 'low' },
  persian: { label: 'Persian', short: 'Per', tier: 'low' },
  maine: { label: 'Maine Coon', short: 'Mai', tier: 'mid' },
  bengal: { label: 'Bengal', short: 'Ben', tier: 'mid' },
  sphynx: { label: 'Sphynx', short: 'Sph', tier: 'high' },
  black_tail: { label: 'BlackTail', short: 'Wild', tier: 'wild' },
}

export const PAYTABLE: Record<ReelSymbolId, Record<3 | 4 | 5, number>> = {
  siamese: { 3: 1, 4: 2, 5: 4 },
  persian: { 3: 1, 4: 3, 5: 6 },
  maine: { 3: 2, 4: 5, 5: 12 },
  bengal: { 3: 3, 4: 7, 5: 22 },
  sphynx: { 3: 4, 4: 12, 5: 45 },
  black_tail: { 3: 6, 4: 20, 5: 80 },
}

export const PAYLINES: number[][] = [
  [0, 0, 0, 0, 0],
  [1, 1, 1, 1, 1],
  [2, 2, 2, 2, 2],
]

/** Same strips as LuckyReelsConfig::defaultReelStrips() */
export const REEL_STRIPS: ReelSymbolId[][] = [
  [
    'siamese', 'persian', 'siamese', 'persian', 'siamese',
    'maine', 'siamese', 'persian', 'siamese', 'bengal',
    'persian', 'siamese', 'persian', 'siamese', 'maine',
    'persian', 'siamese', 'sphynx', 'persian', 'black_tail',
  ],
  [
    'persian', 'siamese', 'persian', 'siamese', 'persian',
    'maine', 'persian', 'siamese', 'persian', 'bengal',
    'siamese', 'persian', 'siamese', 'persian', 'maine',
    'siamese', 'persian', 'sphynx', 'siamese', 'black_tail',
  ],
  [
    'siamese', 'persian', 'maine', 'siamese', 'persian',
    'siamese', 'persian', 'siamese', 'bengal', 'persian',
    'siamese', 'persian', 'maine', 'siamese', 'persian',
    'siamese', 'persian', 'sphynx', 'siamese', 'black_tail',
  ],
  [
    'persian', 'siamese', 'bengal', 'persian', 'siamese',
    'persian', 'maine', 'siamese', 'persian', 'siamese',
    'persian', 'siamese', 'maine', 'persian', 'siamese',
    'bengal', 'persian', 'sphynx', 'siamese', 'black_tail',
  ],
  [
    'siamese', 'persian', 'siamese', 'persian', 'maine',
    'siamese', 'persian', 'siamese', 'persian', 'bengal',
    'siamese', 'persian', 'siamese', 'persian', 'maine',
    'siamese', 'persian', 'sphynx', 'siamese', 'black_tail',
  ],
]

export type LineWin = {
  paylineIndex: number
  symbol: ReelSymbolId
  matchCount: number
  multiplier: number
}

export type SpinResult = {
  stops: number[]
  grid: ReelSymbolId[][]
  lineWins: LineWin[]
  totalMultiplier: number
  winMinor: number
}

function windowFromStrip(strip: ReelSymbolId[], stop: number): ReelSymbolId[] {
  const n = strip.length
  return Array.from({ length: ROWS }, (_, row) => strip[(stop + row) % n])
}

function matchLine(symbols: ReelSymbolId[]): { symbol: ReelSymbolId; count: number } {
  if (symbols.every((s) => s === 'black_tail')) {
    return { symbol: 'black_tail', count: symbols.length }
  }

  let target: ReelSymbolId | null = null
  let count = 0
  for (const s of symbols) {
    if (s === 'black_tail') {
      count += 1
      continue
    }
    if (target === null) {
      target = s
      count += 1
      continue
    }
    if (s === target) {
      count += 1
      continue
    }
    break
  }

  return { symbol: target ?? 'black_tail', count }
}

export function evaluateStops(stops: number[], betMinor: number): SpinResult {
  const grid = stops.map((stop, reel) => windowFromStrip(REEL_STRIPS[reel], stop))
  const lineWins: LineWin[] = []
  let totalMultiplier = 0

  PAYLINES.forEach((line, paylineIndex) => {
    const symbols = line.map((row, reel) => grid[reel][row])
    const { symbol, count } = matchLine(symbols)
    if (count < 3) return
    const multiplier = PAYTABLE[symbol][count as 3 | 4 | 5] ?? 0
    if (multiplier <= 0) return
    totalMultiplier += multiplier
    lineWins.push({ paylineIndex, symbol, matchCount: count, multiplier })
  })

  return {
    stops,
    grid,
    lineWins,
    totalMultiplier,
    winMinor: betMinor * totalMultiplier,
  }
}

export function randomStops(rng: () => number = Math.random): number[] {
  return REEL_STRIPS.map((strip) => Math.floor(rng() * strip.length))
}

export function spin(betMinor: number, rng: () => number = Math.random): SpinResult {
  return evaluateStops(randomStops(rng), betMinor)
}
