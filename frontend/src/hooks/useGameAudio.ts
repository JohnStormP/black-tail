import { useCallback, useState } from 'react'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import {
  isSoundMuted,
  playCashout,
  playLoseThud,
  playSpinTick,
  playWinChime,
  setSoundMuted,
} from '@/lib/sound'

export function useGameAudio() {
  const reduced = usePrefersReducedMotion()
  const [muted, setMuted] = useState(() => isSoundMuted())

  const effectivelyMuted = muted || reduced

  const toggleMute = useCallback(() => {
    if (reduced) return
    setMuted((current) => {
      const next = !current
      setSoundMuted(next)
      return next
    })
  }, [reduced])

  const tick = useCallback(() => {
    if (!effectivelyMuted) playSpinTick()
  }, [effectivelyMuted])

  const win = useCallback(() => {
    if (!effectivelyMuted) playWinChime()
  }, [effectivelyMuted])

  const lose = useCallback(() => {
    if (!effectivelyMuted) playLoseThud()
  }, [effectivelyMuted])

  const cashout = useCallback(() => {
    if (!effectivelyMuted) playCashout()
  }, [effectivelyMuted])

  return {
    muted: effectivelyMuted,
    reduced,
    toggleMute,
    tick,
    win,
    lose,
    cashout,
  }
}
