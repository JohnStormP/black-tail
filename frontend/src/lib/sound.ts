const MUTE_KEY = 'blacktail.soundMuted'

let ctx: AudioContext | null = null

function getCtx(): AudioContext | null {
  if (typeof window === 'undefined') return null
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return null
  if (localStorage.getItem(MUTE_KEY) === '1') return null
  if (!ctx) {
    const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!AudioCtx) return null
    ctx = new AudioCtx()
  }
  return ctx
}

export function isSoundMuted(): boolean {
  return localStorage.getItem(MUTE_KEY) === '1'
}

export function setSoundMuted(muted: boolean) {
  localStorage.setItem(MUTE_KEY, muted ? '1' : '0')
}

function tone(freq: number, durationMs: number, type: OscillatorType = 'sine', gain = 0.04) {
  const audio = getCtx()
  if (!audio) return
  if (audio.state === 'suspended') void audio.resume()
  const osc = audio.createOscillator()
  const amp = audio.createGain()
  osc.type = type
  osc.frequency.value = freq
  amp.gain.value = gain
  osc.connect(amp)
  amp.connect(audio.destination)
  const now = audio.currentTime
  amp.gain.setValueAtTime(gain, now)
  amp.gain.exponentialRampToValueAtTime(0.001, now + durationMs / 1000)
  osc.start(now)
  osc.stop(now + durationMs / 1000)
}

export function playSpinTick() {
  tone(420, 60, 'triangle', 0.03)
}

export function playWinChime() {
  tone(660, 90, 'sine', 0.045)
  setTimeout(() => tone(880, 120, 'sine', 0.04), 90)
}

export function playLoseThud() {
  tone(160, 140, 'square', 0.025)
}

export function playCashout() {
  tone(520, 70, 'sine', 0.04)
  setTimeout(() => tone(780, 100, 'sine', 0.035), 70)
}
