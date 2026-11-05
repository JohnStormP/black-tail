import { useEffect, useMemo, useRef, useState } from 'react'
import { Plane, Volume2, VolumeX } from 'lucide-react'
import { cashOutCrashBet, openCrashRound, placeCrashBet, startCrashRound } from '@/api/crashApi'
import { ApiError } from '@/api/http'
import { getWallet, listWallets } from '@/api/walletsApi'
import { Breadcrumb, Button, Card, IconButton, Input, useToast } from '@/components/ui'
import { useGameAudio } from '@/hooks/useGameAudio'
import { useI18n } from '@/i18n/I18nContext'
import { PLAYER_ID, WALLET_ID } from '@/lib/demoIds'
import { formatMinor } from '@/lib/format'
import { newUlid } from '@/lib/ulid'

const BET_OPTIONS_MINOR = [25, 50, 100, 200, 500, 1000] as const
const CASHOUT_OPTIONS = [1.5, 2, 2.5, 3, 5] as const
const MIN_HUNDREDTHS = 100
const MAX_HUNDREDTHS = 10_000
const MS_PER_HUNDREDTH = 50
const SUPER_TOAST = 'Login as super@blacktail.demo to run crash'
const ROUND_HISTORY_KEY = 'bt-crash-rounds'
const ROUND_HISTORY_MAX = 8

type Phase = 'idle' | 'arming' | 'flying'
type Outcome = 'none' | 'cashed' | 'busted'

type RoundHistoryItem = {
  betMinor: number
  winMinor: number
  multiplier: number
  target: number
  outcome: 'cashed' | 'busted'
  at: number
}

function loadRoundHistory(): RoundHistoryItem[] {
  try {
    const raw = sessionStorage.getItem(ROUND_HISTORY_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw) as RoundHistoryItem[]
    if (!Array.isArray(parsed)) return []
    return parsed.slice(-ROUND_HISTORY_MAX)
  } catch {
    return []
  }
}

function apiErrorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message
  if (err instanceof Error && err.message) return err.message
  return 'Request failed'
}

function isForbidden(err: unknown): boolean {
  return err instanceof ApiError && err.status === 403
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

function multProgress(mult: number): number {
  const clamped = Math.min(Math.max(mult, 1), 5)
  return (clamped - 1) / 4
}

/** Point on the flight curve in SVG viewBox 0..100 */
function flightPoint(progress: number): { x: number; y: number } {
  const p = Math.min(Math.max(progress, 0), 1)
  const eased = p * p * 0.55 + p * 0.45
  return {
    x: 6 + eased * 88,
    y: 92 - eased * 78,
  }
}

function flightCurvePath(progress: number): string {
  const end = flightPoint(progress)
  const mid = flightPoint(progress * 0.55)
  return `M 6 92 Q ${mid.x} ${mid.y + (92 - mid.y) * 0.35} ${end.x} ${end.y}`
}

function hundredthsAt(elapsedMs: number): number {
  return Math.min(MAX_HUNDREDTHS, MIN_HUNDREDTHS + Math.floor(Math.max(0, elapsedMs) / MS_PER_HUNDREDTH))
}

export function CrashPage() {
  const { t } = useI18n()
  const toast = useToast()
  const audio = useGameAudio()
  const mountedRef = useRef(true)
  const flightRef = useRef<number | null>(null)
  const cashingOutRef = useRef(false)
  const betIdRef = useRef<string | null>(null)
  const startedAtMsRef = useRef(0)
  const targetHundredthsRef = useRef(200)
  const cashOutRef = useRef<() => Promise<void>>(async () => {})
  const [betMinor, setBetMinor] = useState(100)
  const [cashoutAt, setCashoutAt] = useState(2)
  const [balanceMinor, setBalanceMinor] = useState<number | null>(null)
  const [phase, setPhase] = useState<Phase>('idle')
  const [outcome, setOutcome] = useState<Outcome>('none')
  const [multiplier, setMultiplier] = useState(1)
  const [lastWin, setLastWin] = useState(0)
  const [cashingOut, setCashingOut] = useState(false)
  const [roundHistory, setRoundHistory] = useState<RoundHistoryItem[]>(() => loadRoundHistory())
  const roundBetRef = useRef(100)
  const roundTargetRef = useRef(2)

  useEffect(() => {
    sessionStorage.setItem(ROUND_HISTORY_KEY, JSON.stringify(roundHistory))
  }, [roundHistory])

  const pushRoundHistory = (entry: Omit<RoundHistoryItem, 'at'>) => {
    setRoundHistory((prev) =>
      [...prev, { ...entry, at: Date.now() }].slice(-ROUND_HISTORY_MAX),
    )
  }

  const stopFlight = () => {
    if (flightRef.current !== null) {
      window.clearInterval(flightRef.current)
      flightRef.current = null
    }
  }

  const reloadBalance = async () => {
    const loaded = await loadDemoWallet()
    if (!mountedRef.current) return
    if ('available' in loaded) setBalanceMinor(loaded.available)
  }

  const finishCashOut = async () => {
    if (cashingOutRef.current) return
    const betId = betIdRef.current
    if (!betId) return
    cashingOutRef.current = true
    stopFlight()
    setCashingOut(true)
    try {
      const settled = await cashOutCrashBet(betId, {
        settleEntryId: newUlid(),
        payoutEntryId: newUlid(),
      })
      if (!mountedRef.current) return
      const payout = settled.payoutMinor ?? 0
      const cashedMult =
        settled.cashOutHundredths != null ? settled.cashOutHundredths / 100 : multiplier
      if (settled.cashOutHundredths != null) {
        setMultiplier(cashedMult)
      }
      setLastWin(payout)
      setOutcome('cashed')
      setBalanceMinor((current) => (current ?? 0) + payout)
      pushRoundHistory({
        betMinor: roundBetRef.current,
        winMinor: payout,
        multiplier: cashedMult,
        target: roundTargetRef.current,
        outcome: 'cashed',
      })
      audio.cashout()
      toast.push({
        title: t('games.cashedOut'),
        description: `x${cashedMult.toFixed(2)} · +${formatMinor(payout)}`,
        tone: 'success',
      })
    } catch {
      if (!mountedRef.current) return
      setLastWin(0)
      setOutcome('busted')
      pushRoundHistory({
        betMinor: roundBetRef.current,
        winMinor: 0,
        multiplier,
        target: roundTargetRef.current,
        outcome: 'busted',
      })
      audio.lose()
      toast.push({
        title: t('games.crashed'),
        description: t('games.crashedHint', { target: roundTargetRef.current }),
        tone: 'danger',
      })
    } finally {
      betIdRef.current = null
      await reloadBalance()
      cashingOutRef.current = false
      if (mountedRef.current) {
        setPhase('idle')
        setCashingOut(false)
      }
    }
  }

  cashOutRef.current = finishCashOut

  useEffect(() => {
    mountedRef.current = true
    void (async () => {
      const loaded = await loadDemoWallet()
      if (!mountedRef.current) return
      if ('available' in loaded) setBalanceMinor(loaded.available)
      else toast.push({ title: 'Wallet unavailable', description: loaded.error, tone: 'danger' })
    })()
    return () => {
      mountedRef.current = false
      stopFlight()
    }
  }, [toast])

  const failStart = (err: unknown, superAction: boolean) => {
    setPhase('idle')
    setCashingOut(false)
    if (superAction && isForbidden(err)) {
      toast.push({ title: SUPER_TOAST, tone: 'danger' })
      return
    }
    toast.push({ title: 'Crash bet failed', description: apiErrorMessage(err), tone: 'danger' })
  }

  const play = async () => {
    if (phase !== 'idle' || cashingOutRef.current) return
    if (betMinor < 25) {
      toast.push({ title: t('games.minBet'), tone: 'warning' })
      return
    }
    if (balanceMinor === null) {
      toast.push({ title: 'Wallet still loading', tone: 'warning' })
      return
    }
    if (balanceMinor < betMinor) {
      toast.push({ title: t('games.insufficient'), tone: 'danger' })
      return
    }
    const targetHundredths = Math.round(cashoutAt * 100)
    if (!Number.isFinite(targetHundredths) || targetHundredths <= MIN_HUNDREDTHS || targetHundredths > MAX_HUNDREDTHS) {
      toast.push({ title: 'Cash out must be above x1.00 and at most x100', tone: 'warning' })
      return
    }

    setPhase('arming')
    setOutcome('none')
    setLastWin(0)
    setMultiplier(1)
    roundBetRef.current = betMinor
    roundTargetRef.current = cashoutAt
    cashingOutRef.current = false
    audio.tick()

    const roundId = newUlid()
    const betId = newUlid()

    try {
      await openCrashRound(roundId)
    } catch (err) {
      failStart(err, true)
      return
    }

    try {
      await placeCrashBet({
        betId,
        roundId,
        playerId: PLAYER_ID,
        walletId: WALLET_ID,
        reserveEntryId: newUlid(),
        amountMinor: betMinor,
        currency: 'USD',
      })
    } catch (err) {
      failStart(err, false)
      return
    }

    betIdRef.current = betId
    setBalanceMinor((current) => (current ?? 0) - betMinor)

    try {
      await startCrashRound(roundId)
    } catch (err) {
      betIdRef.current = null
      failStart(err, true)
      await reloadBalance()
      return
    }

    if (!mountedRef.current) return

    startedAtMsRef.current = Date.now()
    targetHundredthsRef.current = targetHundredths
    setPhase('flying')

    flightRef.current = window.setInterval(() => {
      if (!mountedRef.current) {
        stopFlight()
        return
      }
      const hundredths = hundredthsAt(Date.now() - startedAtMsRef.current)
      setMultiplier(hundredths / 100)
      if (hundredths >= targetHundredthsRef.current || hundredths >= MAX_HUNDREDTHS) {
        void cashOutRef.current()
      }
    }, MS_PER_HUNDREDTH)
  }

  const busy = phase !== 'idle'
  const status = useMemo(() => {
    if (phase === 'arming' || phase === 'flying') return t('games.inFlight')
    if (outcome === 'cashed') return t('games.win')
    if (outcome === 'busted') return t('games.busted')
    return t('games.ready')
  }, [phase, outcome, t])

  const stageClass = useMemo(() => {
    if (phase === 'flying' || phase === 'arming') return 'is-running'
    if (outcome === 'cashed') return 'is-win'
    if (outcome === 'busted') return 'is-busted'
    return ''
  }, [phase, outcome])

  const flightProgress = multProgress(multiplier)
  const targetProgress = multProgress(cashoutAt)
  const plane = flightPoint(flightProgress)
  const targetPoint = flightPoint(targetProgress)
  const curvePath = flightCurvePath(Math.max(flightProgress, 0.02))
  /* Lucide Plane points up-right; align with the rising curve. */
  const planeAngle = 35 + flightProgress * 20

  return (
    <div className="bt-page bt-stack">
      <Breadcrumb items={[{ label: t('group.games'), to: '/games' }, { label: t('nav.crash') }]} />
      <div className="bt-page-header">
        <div>
          <h1 className="bt-page-title">{t('games.crashTitle')}</h1>
          <p className="bt-page-subtitle">{t('games.crashSub')}</p>
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
        <section className={`bt-game-stage bt-crash-stage ${stageClass}`}>
          <div className="bt-game-stage__glow" aria-hidden />
          <div className="bt-crash-stage__stars" aria-hidden />
          <div className="bt-game-stage__top">
            <div>
              <p className="bt-crash-kicker">BlackTail</p>
              <h2 className="bt-reels-title">Crash</h2>
              <p className="bt-reels-sub">{t('games.crashStageSub')}</p>
            </div>
            <div
              className={`bt-reels-winmeter ${outcome === 'cashed' && lastWin > 0 ? 'is-hot' : ''}`}
              aria-live="polite"
            >
              <span>{t('common.lastWin')}</span>
              <strong className="bt-numeric">{formatMinor(lastWin)}</strong>
            </div>
          </div>

          <div className="bt-game-stage__frame bt-crash-stage__frame">
            <div className="bt-crash-stage__sky">
              <p className="bt-crash-stage__mult" aria-live="polite">
                x{multiplier.toFixed(2)}
              </p>
              <p className="bt-crash-stage__status">{status}</p>

              <svg className="bt-crash-stage__chart" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden>
                <defs>
                  <linearGradient id="bt-crash-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="rgba(45, 212, 191, 0.35)" />
                    <stop offset="100%" stopColor="rgba(45, 212, 191, 0)" />
                  </linearGradient>
                  <linearGradient id="bt-crash-stroke" x1="0" y1="1" x2="1" y2="0">
                    <stop offset="0%" stopColor="rgba(45, 212, 191, 0.15)" />
                    <stop offset="100%" stopColor="rgba(94, 234, 212, 1)" />
                  </linearGradient>
                </defs>
                <path
                  className="bt-crash-stage__area"
                  d={`${curvePath} L ${plane.x} 100 L 6 100 Z`}
                  fill="url(#bt-crash-fill)"
                />
                <path className="bt-crash-stage__line" d={curvePath} fill="none" stroke="url(#bt-crash-stroke)" />
                <line
                  className="bt-crash-stage__target"
                  x1="0"
                  x2="100"
                  y1={targetPoint.y}
                  y2={targetPoint.y}
                />
              </svg>

              <div
                className="bt-crash-stage__target-label"
                style={{ top: `${targetPoint.y}%` }}
              >
                {t('form.autoCashout')} x{cashoutAt}
              </div>

              <div
                className="bt-crash-stage__plane"
                style={{
                  left: `${plane.x}%`,
                  top: `${plane.y}%`,
                  transform: `translate(-50%, -50%) rotate(${planeAngle}deg)`,
                }}
                aria-hidden
              >
                <span className="bt-crash-stage__plane-glow" />
                <Plane className="bt-crash-stage__plane-svg" strokeWidth={2.25} absoluteStrokeWidth />
              </div>
            </div>
          </div>

          <div className="bt-game-stage__controls">
            <div className="bt-bet-group">
              <span className="bt-label">{t('common.bet')}</span>
              <div className="bt-bet-options bt-game-bet-chips">
                {BET_OPTIONS_MINOR.map((amount) => (
                  <button
                    key={amount}
                    type="button"
                    className={`bt-bet-chip ${betMinor === amount ? 'bt-bet-chip--active' : ''}`}
                    onClick={() => setBetMinor(amount)}
                    disabled={busy}
                  >
                    {formatMinor(amount)}
                  </button>
                ))}
              </div>
              <span className="bt-label" style={{ marginTop: 12 }}>
                {t('form.autoCashout')}
              </span>
              <div className="bt-game-chip-row">
                {CASHOUT_OPTIONS.map((value) => (
                  <button
                    key={value}
                    type="button"
                    className={`bt-game-chip ${cashoutAt === value ? 'bt-game-chip--active' : ''}`}
                    onClick={() => setCashoutAt(value)}
                    disabled={busy}
                  >
                    x{value}
                  </button>
                ))}
              </div>
              <Input
                label={t('form.customCashout')}
                type="number"
                min={1.01}
                max={100}
                step={0.01}
                value={cashoutAt}
                onChange={(e) => setCashoutAt(Number(e.target.value))}
                disabled={busy}
              />
            </div>
            <Button
              className={phase === 'flying' ? 'bt-crash-cashout' : 'bt-crash-play'}
              size="lg"
              loading={phase === 'arming' || cashingOut}
              disabled={balanceMinor === null && phase === 'idle'}
              onClick={() => {
                if (phase === 'flying') void finishCashOut()
                else void play()
              }}
            >
              {phase === 'flying' ? t('games.cashOut') : t('games.placeBet')}
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
                <span>{t('common.bet')}</span>
                <strong className="bt-numeric">{formatMinor(betMinor)}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('form.autoCashout')}</span>
                <strong className="bt-numeric">x{cashoutAt}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('games.multiplier')}</span>
                <strong className="bt-numeric">x{multiplier.toFixed(2)}</strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('common.lastWin')}</span>
                <strong
                  className="bt-numeric"
                  style={{ color: lastWin > 0 ? 'var(--bt-success)' : undefined }}
                >
                  {formatMinor(lastWin)}
                </strong>
              </div>
              <div className="bt-spin-result__row">
                <span>{t('common.status')}</span>
                <strong>{status}</strong>
              </div>
            </div>
          </Card>

          <Card title={t('games.lastRounds')}>
            {roundHistory.length === 0 ? (
              <p style={{ margin: 0, color: 'var(--bt-text-faint)', fontSize: 13 }}>{t('games.noRounds')}</p>
            ) : (
              <div className="bt-metric-list">
                {[...roundHistory].reverse().map((entry, index) => (
                  <div className="bt-metric-row" key={`${entry.at}-${index}`}>
                    <span>
                      {t('common.bet')} {formatMinor(entry.betMinor)} · x{entry.multiplier.toFixed(2)}
                      {entry.outcome === 'busted' ? ` · ${t('games.crashed')}` : ''}
                    </span>
                    <strong
                      className="bt-numeric"
                      style={{
                        color: entry.winMinor > 0 ? 'var(--bt-success)' : 'var(--bt-text-muted)',
                      }}
                    >
                      {formatMinor(entry.winMinor)}
                    </strong>
                  </div>
                ))}
              </div>
            )}
          </Card>

          <Card title={t('games.howToPlay')} footer={t('games.crashHowToFooter')}>
            <div className="bt-metric-list">
              <div className="bt-metric-row">
                <span>{t('games.crashHowTo1')}</span>
              </div>
              <div className="bt-metric-row">
                <span>{t('games.crashHowTo2')}</span>
              </div>
              <div className="bt-metric-row">
                <span>{t('games.crashHowTo3')}</span>
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  )
}
