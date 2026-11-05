function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  const match = /^#([0-9A-Fa-f]{6})$/.exec(hex)
  if (!match) return null
  const n = Number.parseInt(match[1], 16)
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 }
}

function lighten(hex: string, amount: number): string {
  const rgb = hexToRgb(hex)
  if (!rgb) return hex
  const mix = (channel: number) => Math.min(255, Math.round(channel + (255 - channel) * amount))
  return `#${[mix(rgb.r), mix(rgb.g), mix(rgb.b)].map((v) => v.toString(16).padStart(2, '0')).join('')}`
}

export function applyAccent(hex: string) {
  if (!hexToRgb(hex)) return
  const root = document.documentElement
  root.style.setProperty('--bt-accent', hex)
  root.style.setProperty('--bt-accent-hover', lighten(hex, 0.25))
  root.style.setProperty('--bt-border-focus', hex)
  root.style.setProperty('--bt-chart-1', hex)
  const rgb = hexToRgb(hex)!
  root.style.setProperty('--bt-accent-muted', `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.12)`)
  root.style.setProperty('--bt-accent-border', `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.35)`)
}
