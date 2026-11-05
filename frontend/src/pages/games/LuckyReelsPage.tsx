import { useEffect, useMemo, useRef, useState } from 'react'
import { Volume2, VolumeX } from 'lucide-react'
import { spinLuckyReels, type GameRoundResponse } from '@/api/gamesApi'
import { ApiError } from '@/api/http'
import { getWallet, listWallets } from '@/api/walletsApi'
import { CatSymbol } from '@/components/games/CatSymbol'
import { Breadcrumb, Button, Card, IconButton, useToast } from '@/components/ui'
import { useGameAudio } from '@/hooks/useGameAudio'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { useI18n } from '@/i18n/I18nContext'
import { LUCKY_REELS_GAME_ID, PLAYER_ID, WALLET_ID } from '@/lib/demoIds'
import { formatMinor } from '@/lib/format'
import {
  PAYLINES,
  PAYTABLE,
  REEL_STRIPS,
  SYMBOL_META,
  evaluateStops,
  type ReelSymbolId,
  type SpinResult,
} from '@/lib/lucky-reels'
import { newUlid } from '@/lib/ulid'

const BET_OPTIONS_MINOR = [10, 25, 50, 100, 200, 500, 1000] as const
const SPIN_HISTORY_KEY = 'bt-lucky-reels-spins'
const SPIN_HISTORY_MAX = 8

type SpinHistoryItem = {
  betMinor: number
  winMinor: number
  totalMultiplier: number
  at: number
}

function apiErrorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message
  if (err instanceof Error && err.message) return err.message
  return 'Request failed'
}

async function loadDemoWallet(): Promise<{ available: number } | { error: string }> {
  try {
    const wallet = await getWallet(WALLET_ID)
    return { available: wallet.available }
  } catch (first) {
    try {
      const wallets = await listWallets(PLAYER_ID)
      const match = wallets.find((wallet) => wallet.id === WALLET_ID) ?? wallets.find((wallet) => wallet.currency === 'USD')
      if (!match) return { error: apiErrorMessage(first) }
      return { available: match.available }
    } catch (second) {
      return { error: apiErrorMessage(second) }
    }
  }
}

function loadSpinHistory(): SpinHistoryItem[] {
  try {
    const raw = sessionStorage.getItem(SPIN_HISTORY_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw) as SpinHistoryItem[]
    if (!Array.isArray(parsed)) return []
    return parsed.slice(-SPIN_HISTORY_MAX)
  } catch {
    return []
  }
}

function emptyGrid(): ReelSymbolId[][] {
  return Array.from({ length: 5 }, () =>
    Array.from({ length: 3 }, () => 'siamese' as ReelSymbolId),
  )
}

function cloneGrid(grid: ReelSymbolId[][]): ReelSymbolId[][] {
  return grid.map((col) => [...col])
}

function gridStops(stops: number[]): number[] {
  return Array.from({ length: REEL_STRIPS.length }, (_, reel) => {
    const length = REEL_STRIPS[reel].length
    const stop = stops[reel] ?? 0
    const normalized = Number.isFinite(stop) ? Math.trunc(stop) : 0
    return ((normalized % length) + length) % length
  })
}

const INITIAL_GRID = evaluateStops([0, 0, 0, 0, 0], 0).grid
const INITIAL_RESULT: SpinResult = {
  stops: [],
  grid: INITIAL_GRID,
  lineWins: [],
  totalMultiplier: 0,
  winMinor: 0,
}

export function LuckyReelsPage() {
  const { t } = useI18n()
  const toast = useToast()
  const audio = useGameAudio()
  const reducedMotion = usePrefersReducedMotion()
  const mountedRef = useRef(true)
  const [betMinor, setBetMinor] = useState<number>(100)
  const [spinning, setSpinning] = useState(false)
  const [settlingCol, setSettlingCol] = useState<number | null>(null)
  const [balanceMinor, setBalanceMinor] = useState<number | null>(null)
  const [spinHistory, setSpinHistory] = useState<SpinHistoryItem[]>(() => loadSpinHistory())
  const [result, setResult] = useState<SpinResult>(INITIAL_RESULT)
  const [displayGrid, setDisplayGrid] = useState<ReelSymbolId[][]>(() => cloneGrid(INITIAL_GRID))

  useEffect(() => {
    mountedRef.current = true
    return () => {
      mountedRef.current = false
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      const loaded = await loadDemoWallet()
      if (cancelled) return
      if ('available' in loaded) setBalanceMinor(loaded.available)
      else toast.push({ title: 'Wallet unavailable', description: loaded.error, tone: 'danger' })
    })()
    return () => {
      cancelled = true
    }
  }, [toast])

  useEffect(() => {
    sessionStorage.setItem(SPIN_HISTORY_KEY, JSON.stringify(spinHistory))
  }, [spinHistory])

  const winningCells = useMemo(() => {
    const cells = new Set<string>()
    for (const win of result.lineWins) {
      const line = PAYLINES[win.paylineIndex]
      for (let reel = 0; reel < win.matchCount; reel += 1) {
        cells.add(`${reel}:${line[reel]}`)
      }
    }
    return cells
  }, [result.lineWins])

  const applyWallet = async (fallback: number | null) => {
    const loaded = await loadDemoWallet()
    if (!mountedRef.current) return
    if ('available' in loaded) {
      setBalanceMinor(loaded.available)
      return
    }
    if (fallback !== null) setBalanceMinor(fallback)
    else toast.push({ title: 'Wallet unavailable', description: loaded.error, tone: 'warning' })
  }

  const onSpin = async () => {
    if (spinning) return
    if (balanceMinor === null) {
      toast.push({ title: 'Wallet still loading', tone: 'warning' })
      return
    }
    if (balanceMinor < betMinor) {
      toast.push({ title: t('games.insufficient'), tone: 'danger' })
      return
    }

    setSpinning(true)
    setSettlingCol(null)
    setBalanceMinor((current) => (current ?? 0) - betMinor)
    audio.tick()

    const apiPromise: Promise<GameRoundResponse> = spinLuckyReels({
      roundId: newUlid(),
      gameId: LUCKY_REELS_GAME_ID,
      playerId: PLAYER_ID,
      walletId: WALLET_ID,
      betMinor,
      currency: 'USD',
      reserveEntryId: newUlid(),
      settleEntryId: newUlid(),
      payoutEntryId: newUlid(),
    })

    const shuffleDelays = reducedMotion ? [40] : [95, 88, 78, 68, 58, 50, 45, 40]
    for (let i = 0; i < shuffleDelays.length; i += 1) {
      if (!mountedRef.current) return
      setDisplayGrid(
        emptyGrid().map((col) =>
          col.map(() => {
            const keys = Object.keys(SYMBOL_META) as ReelSymbolId[]
            return keys[Math.floor(Math.random() * keys.length)]
          }),
        ),
      )
      await new Promise((resolve) => setTimeout(resolve, shuffleDelays[i]))
    }

    let round: GameRoundResponse
    try {
      round = await apiPromise
    } catch (err) {
      if (!mountedRef.current) return
      setDisplayGrid(cloneGrid(result.grid))
      setSpinning(false)
      setSettlingCol(null)
      toast.push({ title: 'Spin failed', description: apiErrorMessage(err), tone: 'danger' })
      await applyWallet((balanceMinor ?? 0))
      return
    }

    if (!mountedRef.current) return

    const evaluated = evaluateStops(gridStops(round.stops), round.betMinor)
    const next: SpinResult = {
      ...evaluated,
      stops: round.stops,
      totalMultiplier: round.totalMultiplier,
      winMinor: round.winMinor,
    }
    const finalGrid = cloneGrid(next.grid)

    if (reducedMotion) {
      setDisplayGrid(finalGrid)
    } else {
      const partial = emptyGrid()
      for (let colIndex = 0; colIndex < 5; colIndex += 1) {
        if (!mountedRef.current) return
        for (let rowIndex = 0; rowIndex < 3; rowIndex += 1) {
          partial[colIndex][rowIndex] = finalGrid[colIndex][rowIndex]
        }
        setSettlingCol(colIndex)
        setDisplayGrid(cloneGrid(partial))
        audio.tick()
        await new Promise((resolve) => setTimeout(resolve, 85 + colIndex * 35))
      }
    }

    if (!mountedRef.current) return

    setResult(next)
    setDisplayGrid(finalGrid)
    setBalanceMinor((current) => (current ?? 0) + next.winMinor)
    setSpinning(false)
    setSettlingCol(null)

    setSpinHistory((prev) =>
      [
        ...prev,
        {
          betMinor: round.betMinor,
          winMinor: round.winMinor,
          totalMultiplier: round.totalMultiplier,
          at: Date.now(),
        },
      ].slice(-SPIN_HISTORY_MAX),
    )

    if (next.winMinor > 0) audio.win()
    else audio.lose()

    toast.push({
      title: next.winMinor > 0 ? t('games.win') : t('games.noWin'),
      description:
        next.winMinor > 0
          ? `+${formatMinor(next.winMinor)} · x${next.totalMultiplier} · ${next.lineWins.length}`
          : t('games.noLines'),
      tone: next.winMinor > 0 ? 'success' : 'neutral',
    })

    await applyWallet(null)
  }

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.games'), to: '/games' }, { label: t('nav.luckyReels') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('games.luckyTitle')}</h1>
          <p className="bt-page-subtitle">{t('games.luckySub')}</p>
        </div>
        <div className="bt-page-actions">
          <IconButton
            label={audio.muted ? t('games.soundOff') : t('games.soundOn')}
            onClick={audio.toggleMute}
          >
            {audio.muted ? <VolumeX size={16} /> : <Volume2 size={16} />}
          </IconButton>
        </div>
      </div>

      <div className="bt-reels-layout">
        <section
          className={[
            'bt-reels-stage',
            'bt-reels-stage--cats',
            spinning ? 'is-spinning' : '',
            !spinning && result.winMinor > 0 ? 'is-win' : '',
          ]
            .filter(Boolean)
            .join(' ')}
        >
          <div className="bt-reels-stage__glow" aria-hidden />
          <div className="bt-reels-stage__vignette" aria-hidden />
          <div className="bt-reels-stage__top">
            <div>
              <p className="bt-reels-kicker">BlackTail</p>
              <h2 className="bt-reels-title">Lucky Reels</h2>
              <p className="bt-reels-sub">5×3 cat slot · BlackTail wild</p>
            </div>
            <div
              className={`bt-reels-winmeter ${!spinning && result.winMinor > 0 ? 'is-hot' : ''}`}
              aria-live="polite"
            >
              <span>{t('common.lastWin')}</span>
              <strong className="bt-numeric">{formatMinor(result.winMinor)}</strong>
            </div>
          </div>

          <div className="bt-reels-frame">
            <div className="bt-reels-frame__shine" aria-hidden />
            {PAYLINES.map((_, index) => (
              <div
                key={index}
                className={`bt-payline bt-payline--${index} ${result.lineWins.some((w) => w.paylineIndex === index) ? 'is-active' : ''}`}
                aria-hidden
              />
            ))}
            <div className="bt-reels-grid" aria-live="polite">
              {displayGrid.map((col, colIndex) => (
                <div
                  className={`bt-reel-col ${settlingCol === colIndex ? 'is-settling' : ''} ${spinning && settlingCol !== colIndex ? 'is-spinning' : ''}`}
                  key={colIndex}
                >
                  {col.map((symbol, rowIndex) => (
                    <CatSymbol
                      key={`${colIndex}-${rowIndex}-${result.stops.join('-')}-${spinning ? 's' : 'i'}`}
                      symbol={symbol}
                      spinning={spinning && settlingCol !== colIndex}
                      win={!spinning && winningCells.has(`${colIndex}:${rowIndex}`)}
                    />
                  ))}
                </div>
              ))}
            </div>
          </div>

          <div className="bt-reels-controls">
            <div className="bt-bet-group">
              <span className="bt-label">{t('common.bet')}</span>
              <div className="bt-bet-options bt-game-bet-chips">
                {BET_OPTIONS_MINOR.map((amount) => (
                  <button
                    key={amount}
                    type="button"
                    className={`bt-bet-chip ${betMinor === amount ? 'bt-bet-chip--active' : ''}`}
                    onClick={() => setBetMinor(amount)}
                    disabled={spinning}
                  >
                    {formatMinor(amount)}
                  </button>
                ))}
              </div>
            </div>
            <Button
              className="bt-reels-spin"
              size="lg"
              loading={spinning}
              onClick={() => void onSpin()}
              disabled={balanceMinor === null}
            >
              {t('common.spin')}
            </Button>
          </div>
        </section>

        <div className="bt-stack" style={{ gap: 16 }}>
          <Card title={t('common.session')}>
            <div className="bt-spin-result">
              <div className="bt-spin-result__row">
                <span>{t('common.balance')}</span>
                <strong className="bt-numeric">{balanceMinor === null ? '-' : formatMinor(balanceMinor)}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('games.lastBet')}</span>
                <strong className="bt-numeric">{formatMinor(betMinor)}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('games.multiplier')}</span>
                <strong className="bt-numeric">x{result.totalMultiplier}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('common.lastWin')}</span>
                <strong
                  className="bt-numeric"
                  style={{ color: result.winMinor > 0 ? 'var(--bt-success)' : undefined }}
                >
                  {formatMinor(result.winMinor)}
                </strong>
              </div>
            </div>
          </Card>

          <Card title={t('games.lastSpins')}>
            {spinHistory.length === 0 ? (
              <p style={{ margin: 0, color: 'var(--bt-text-faint)', fontSize: 13 }}>{t('games.noSpins')}</p>
            ) : (
              <div className="bt-metric-list">
                {[...spinHistory].reverse().map((entry, index) => (
                  <div className="bt-metric-row" key={`${entry.at}-${index}`}>
                    <span>
                      {t('common.bet')} {formatMinor(entry.betMinor)} · x{entry.totalMultiplier}
                    </span>
                    <strong
                      className="bt-numeric"
                      style={{ color: entry.winMinor > 0 ? 'var(--bt-success)' : 'var(--bt-text-muted)' }}
                    >
                      {formatMinor(entry.winMinor)}
                    </strong>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title={t('games.lineWins')}>
            {result.lineWins.length === 0 ? (
              <p style={{ margin: 0, color: 'var(--bt-text-faint)', fontSize: 13 }}>{t('games.noLines')}</p>
            ) : (
              <div className="bt-metric-list">
                {result.lineWins.map((win) => (
                  <div className="bt-metric-row" key={`${win.paylineIndex}-${win.symbol}`}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                      <CatSymbol symbol={win.symbol} size="sm" />
                      Line {win.paylineIndex + 1} · {win.matchCount}× {SYMBOL_META[win.symbol].label}
                    </span>
                    <strong className="bt-numeric">x{win.multiplier}</strong>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title={t('games.paytable')} footer={t('games.paytableFooter')}>
            <div className="bt-paytable">
              <div className="bt-paytable__head" aria-hidden>
                <span className="bt-paytable__symbol-col">{t('games.paytableSymbol')}</span>
                <span className="bt-numeric">{t('games.paytableMatch3')}</span>
                <span className="bt-numeric">{t('games.paytableMatch4')}</span>
                <span className="bt-numeric">{t('games.paytableMatch5')}</span>
              </div>
              {(Object.keys(PAYTABLE) as ReelSymbolId[]).map((symbol) => (
                <div className="bt-paytable__row" key={symbol}>
                  <div className="bt-paytable__symbol">
                    <CatSymbol symbol={symbol} size="sm" />
                    <div className="bt-paytable__name">
                      {SYMBOL_META[symbol].label}
                      {symbol === 'black_tail' ? (
                        <span className="bt-paytable__tag">{t('games.paytableWild')}</span>
                      ) : null}
                    </div>
                  </div>
                  <strong className="bt-paytable__odd bt-numeric">×{PAYTABLE[symbol][3]}</strong>
                  <strong className="bt-paytable__odd bt-numeric">×{PAYTABLE[symbol][4]}</strong>
                  <strong className="bt-paytable__odd bt-numeric">×{PAYTABLE[symbol][5]}</strong>
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  )
}
