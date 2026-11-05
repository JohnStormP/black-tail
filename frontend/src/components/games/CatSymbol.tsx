import type { ReelSymbolId } from '@/lib/lucky-reels'
import { SYMBOL_META } from '@/lib/lucky-reels'
import { cn } from '@/lib/cn'

type Props = {
  symbol: ReelSymbolId
  spinning?: boolean
  win?: boolean
  size?: 'md' | 'sm'
}

export function CatSymbol({ symbol, spinning = false, win = false, size = 'md' }: Props) {
  const meta = SYMBOL_META[symbol]
  return (
    <div
      className={cn(
        'bt-cat',
        `bt-cat--${symbol}`,
        size === 'sm' && 'bt-cat--sm',
        spinning && 'bt-cat--spin',
        win && 'bt-cat--win',
      )}
      title={meta.label}
      aria-label={meta.label}
    >
      <svg viewBox="0 0 64 64" className="bt-cat__svg" aria-hidden>
        {symbol === 'siamese' && <SiameseArt />}
        {symbol === 'persian' && <PersianArt />}
        {symbol === 'maine' && <MaineArt />}
        {symbol === 'bengal' && <BengalArt />}
        {symbol === 'sphynx' && <SphynxArt />}
        {symbol === 'black_tail' && <BlackTailArt />}
      </svg>
      <span className="bt-cat__label">{meta.label}</span>
    </div>
  )
}

function Ear({ x, flip = false, fill }: { x: number; flip?: boolean; fill: string }) {
  return (
    <path
      d={flip ? `M${x} 22 L${x + 10} 6 L${x + 14} 24 Z` : `M${x} 22 L${x - 10} 6 L${x - 14} 24 Z`}
      fill={fill}
    />
  )
}

function FaceBase({ fill, stroke }: { fill: string; stroke: string }) {
  return <ellipse cx="32" cy="36" rx="18" ry="16" fill={fill} stroke={stroke} strokeWidth="1.5" />
}

function Eyes({ color, y = 34 }: { color: string; y?: number }) {
  return (
    <>
      <ellipse cx="25" cy={y} rx="3.2" ry="3.8" fill={color} />
      <ellipse cx="39" cy={y} rx="3.2" ry="3.8" fill={color} />
      <circle cx="25.8" cy={y - 0.8} r="1" fill="#fff" opacity="0.85" />
      <circle cx="39.8" cy={y - 0.8} r="1" fill="#fff" opacity="0.85" />
    </>
  )
}

function NoseMouth({ nose = '#f472b6' }: { nose?: string }) {
  return (
    <>
      <path d="M32 38 L29 41 L35 41 Z" fill={nose} />
      <path d="M32 41 Q26 45 22 43" stroke="#334155" strokeWidth="1.2" fill="none" strokeLinecap="round" />
      <path d="M32 41 Q38 45 42 43" stroke="#334155" strokeWidth="1.2" fill="none" strokeLinecap="round" />
    </>
  )
}

function SiameseArt() {
  return (
    <>
      <Ear x={22} fill="#f5e6d3" />
      <Ear x={42} flip fill="#f5e6d3" />
      <FaceBase fill="#fff7ed" stroke="#c4a484" />
      <ellipse cx="22" cy="28" rx="5" ry="4" fill="#5b3a29" opacity="0.35" />
      <ellipse cx="42" cy="28" rx="5" ry="4" fill="#5b3a29" opacity="0.35" />
      <Eyes color="#38bdf8" />
      <NoseMouth />
    </>
  )
}

function PersianArt() {
  return (
    <>
      <Ear x={20} fill="#e7c39a" />
      <Ear x={44} flip fill="#e7c39a" />
      <FaceBase fill="#f0d2a8" stroke="#c9956a" />
      <ellipse cx="32" cy="36" rx="16" ry="14" fill="#f6dfc0" opacity="0.55" />
      <Eyes color="#65a30d" y={35} />
      <NoseMouth nose="#fb7185" />
    </>
  )
}

function MaineArt() {
  return (
    <>
      <Ear x={21} fill="#a16207" />
      <Ear x={43} flip fill="#a16207" />
      <FaceBase fill="#d6a35a" stroke="#92400e" />
      <path d="M18 40 Q32 48 46 40" stroke="#78350f" strokeWidth="2" fill="none" />
      <path d="M24 28 L28 44" stroke="#78350f" strokeWidth="1.4" />
      <path d="M40 28 L36 44" stroke="#78350f" strokeWidth="1.4" />
      <Eyes color="#fbbf24" />
      <NoseMouth nose="#f59e0b" />
    </>
  )
}

function BengalArt() {
  return (
    <>
      <Ear x={22} fill="#c2410c" />
      <Ear x={42} flip fill="#c2410c" />
      <FaceBase fill="#fb923c" stroke="#9a3412" />
      <circle cx="22" cy="30" r="2.2" fill="#7c2d12" />
      <circle cx="42" cy="30" r="2.2" fill="#7c2d12" />
      <circle cx="28" cy="44" r="1.8" fill="#7c2d12" />
      <circle cx="36" cy="44" r="1.8" fill="#7c2d12" />
      <circle cx="32" cy="28" r="1.5" fill="#7c2d12" />
      <Eyes color="#84cc16" />
      <NoseMouth nose="#ea580c" />
    </>
  )
}

function SphynxArt() {
  return (
    <>
      <Ear x={20} fill="#f0ab9a" />
      <Ear x={44} flip fill="#f0ab9a" />
      <FaceBase fill="#fecdd3" stroke="#fb7185" />
      <path d="M20 30 Q32 26 44 30" stroke="#fb7185" strokeWidth="1.2" fill="none" opacity="0.7" />
      <Eyes color="#a78bfa" />
      <NoseMouth nose="#f43f5e" />
    </>
  )
}

function BlackTailArt() {
  return (
    <>
      <Ear x={21} fill="#0f172a" />
      <Ear x={43} flip fill="#0f172a" />
      <FaceBase fill="#111827" stroke="#2dd4bf" />
      <circle cx="32" cy="36" r="17" fill="none" stroke="#2dd4bf" strokeWidth="1.2" opacity="0.45" />
      <Eyes color="#2dd4bf" />
      <NoseMouth nose="#2dd4bf" />
      <text x="32" y="56" textAnchor="middle" fill="#2dd4bf" fontSize="7" fontWeight="700" fontFamily="sans-serif">
        WILD
      </text>
    </>
  )
}
