// 潘朵拉月曆 SFX library — TS port of meal frontend/public/sound.js
// 17 個合成 SFX (Web Audio API, 零音檔) + mute toggle + iOS unlock-on-first-gesture.
//
// Usage:
//   import { useSfx } from '@/lib/sound'
//   const sfx = useSfx()
//   sfx.play('xp')
//   sfx.toggle()
//
// All methods are safe to call before user interaction; audio stays
// suspended until first tap (iOS/Safari unlock).

import { ref, readonly } from 'vue'

const LS_KEY = 'pandora_calendar_sfx_muted'

// --- Module state ---
let ctx: AudioContext | null = null
let master: GainNode | null = null
let unlocked = false

const _muted = ref<boolean>(typeof localStorage !== 'undefined' && localStorage.getItem(LS_KEY) === '1')

function ensureCtx(): AudioContext | null {
  if (ctx) return ctx
  const AC: typeof AudioContext | undefined =
    (window as any).AudioContext || (window as any).webkitAudioContext
  if (!AC) return null
  ctx = new AC()
  master = ctx.createGain()
  master.gain.value = 0.85
  master.connect(ctx.destination)
  return ctx
}

function unlock() {
  if (unlocked) return
  const c = ensureCtx()
  if (!c) return
  if (c.state === 'suspended') c.resume()
  unlocked = true
}

if (typeof window !== 'undefined') {
  const handler = () => {
    unlock()
    window.removeEventListener('touchstart', handler)
    window.removeEventListener('pointerdown', handler)
    window.removeEventListener('keydown', handler)
  }
  window.addEventListener('touchstart', handler, { once: true, passive: true })
  window.addEventListener('pointerdown', handler, { once: true })
  window.addEventListener('keydown', handler, { once: true })
}

// --- Synthesis helpers ---

function now(): number {
  return ctx?.currentTime ?? 0
}

interface ToneOpts {
  freq: number
  dur?: number
  type?: OscillatorType
  gain?: number
  attack?: number
  decay?: number
  detune?: number
  delay?: number
  filter?: { type?: BiquadFilterType; freq?: number; q?: number } | null
}

function tone({
  freq,
  dur = 0.12,
  type = 'sine',
  gain = 0.2,
  attack = 0.005,
  decay,
  detune = 0,
  delay = 0,
  filter = null,
}: ToneOpts) {
  if (!ctx || !master) return
  const t0 = now() + delay
  const osc = ctx.createOscillator()
  const g = ctx.createGain()
  osc.type = type
  osc.frequency.setValueAtTime(freq, t0)
  if (detune) osc.detune.value = detune
  const d = decay != null ? decay : dur
  g.gain.setValueAtTime(0, t0)
  g.gain.linearRampToValueAtTime(gain, t0 + attack)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + attack + d)
  let node: AudioNode = osc
  if (filter) {
    const f = ctx.createBiquadFilter()
    f.type = filter.type || 'lowpass'
    f.frequency.value = filter.freq || 2000
    f.Q.value = filter.q || 1
    osc.connect(f)
    node = f
  }
  node.connect(g)
  g.connect(master)
  osc.start(t0)
  osc.stop(t0 + attack + d + 0.05)
}

interface GlideOpts {
  freqStart: number
  freqEnd: number
  dur?: number
  type?: OscillatorType
  gain?: number
  delay?: number
}

function glide({ freqStart, freqEnd, dur = 0.2, type = 'sine', gain = 0.2, delay = 0 }: GlideOpts) {
  if (!ctx || !master) return
  const t0 = now() + delay
  const osc = ctx.createOscillator()
  const g = ctx.createGain()
  osc.type = type
  osc.frequency.setValueAtTime(freqStart, t0)
  osc.frequency.exponentialRampToValueAtTime(Math.max(1, freqEnd), t0 + dur)
  g.gain.setValueAtTime(0, t0)
  g.gain.linearRampToValueAtTime(gain, t0 + 0.01)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur)
  osc.connect(g)
  g.connect(master)
  osc.start(t0)
  osc.stop(t0 + dur + 0.05)
}

interface NoiseOpts {
  dur?: number
  gain?: number
  filterFreq?: number
  filterType?: BiquadFilterType
  q?: number
  delay?: number
}

function noise({
  dur = 0.08,
  gain = 0.12,
  filterFreq = 2000,
  filterType = 'bandpass',
  q = 2,
  delay = 0,
}: NoiseOpts) {
  if (!ctx || !master) return
  const t0 = now() + delay
  const buf = ctx.createBuffer(1, Math.floor(ctx.sampleRate * dur), ctx.sampleRate)
  const data = buf.getChannelData(0)
  for (let i = 0; i < data.length; i++) data[i] = (Math.random() * 2 - 1) * 0.8
  const src = ctx.createBufferSource()
  src.buffer = buf
  const f = ctx.createBiquadFilter()
  f.type = filterType
  f.frequency.value = filterFreq
  f.Q.value = q
  const g = ctx.createGain()
  g.gain.setValueAtTime(gain, t0)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur)
  src.connect(f)
  f.connect(g)
  g.connect(master)
  src.start(t0)
  src.stop(t0 + dur + 0.02)
}

const PENTA = {
  A4: 440,
  C5: 523.25,
  D5: 587.33,
  E5: 659.25,
  G5: 783.99,
  A5: 880,
  C6: 1046.5,
  D6: 1174.66,
  E6: 1318.51,
  G6: 1567.98,
  A6: 1760,
  C7: 2093,
}

// --- Named FX ---

export type SfxName =
  | 'ui_tap'
  | 'ui_open'
  | 'ui_close'
  | 'card_draw'
  | 'card_flip'
  | 'choice_hover'
  | 'choice_select'
  | 'correct'
  | 'wrong'
  | 'combo'
  | 'xp'
  | 'level_up'
  | 'legendary'
  | 'achievement'
  | 'meal_logged'
  | 'cycle_logged'
  | 'notify'
  | 'box_open'
  | 'pet'
  | 'click'
  | 'open'
  | 'close'

const FX: Record<string, () => void> = {
  ui_tap() {
    tone({ freq: 880, dur: 0.06, type: 'sine', gain: 0.12, decay: 0.05 })
    tone({ freq: 660, dur: 0.05, type: 'sine', gain: 0.08, delay: 0.015, decay: 0.04 })
  },
  ui_open() {
    glide({ freqStart: 440, freqEnd: 880, dur: 0.12, type: 'triangle', gain: 0.14 })
    tone({ freq: PENTA.E6, dur: 0.12, gain: 0.08, delay: 0.08, decay: 0.1 })
  },
  ui_close() {
    glide({ freqStart: 880, freqEnd: 440, dur: 0.12, type: 'triangle', gain: 0.12 })
  },
  card_draw() {
    noise({ dur: 0.28, gain: 0.18, filterFreq: 900, filterType: 'bandpass', q: 1.3 })
    noise({ dur: 0.22, gain: 0.1, filterFreq: 2400, filterType: 'highpass', q: 0.8, delay: 0.04 })
    glide({ freqStart: 220, freqEnd: 880, dur: 0.28, type: 'sine', gain: 0.16 })
    tone({ freq: PENTA.E6, dur: 0.22, type: 'sine', gain: 0.12, delay: 0.25, decay: 0.2 })
    tone({ freq: PENTA.G6, dur: 0.18, type: 'sine', gain: 0.08, delay: 0.3, decay: 0.15 })
  },
  card_flip() {
    noise({ dur: 0.06, gain: 0.2, filterFreq: 2800, filterType: 'bandpass', q: 2.5 })
    tone({ freq: PENTA.G5, dur: 0.12, type: 'triangle', gain: 0.2, decay: 0.1 })
    tone({ freq: PENTA.D6, dur: 0.18, type: 'sine', gain: 0.1, delay: 0.04, decay: 0.15 })
  },
  choice_hover() {
    tone({ freq: PENTA.D6, dur: 0.04, type: 'sine', gain: 0.08, decay: 0.03 })
  },
  choice_select() {
    tone({ freq: PENTA.E6, dur: 0.08, type: 'triangle', gain: 0.16, decay: 0.07 })
    tone({ freq: PENTA.C6, dur: 0.06, type: 'sine', gain: 0.1, delay: 0.03, decay: 0.05 })
  },
  correct() {
    const notes = [PENTA.C6, PENTA.E6, PENTA.G6]
    notes.forEach((f, i) => {
      tone({ freq: f, dur: 0.16, type: 'sine', gain: 0.28, delay: i * 0.08, decay: 0.14 })
      tone({ freq: f * 2, dur: 0.1, type: 'sine', gain: 0.08, delay: i * 0.08 + 0.01, decay: 0.09 })
    })
    tone({ freq: PENTA.C7, dur: 0.35, type: 'sine', gain: 0.14, delay: 0.28, decay: 0.32 })
  },
  wrong() {
    glide({ freqStart: 330, freqEnd: 180, dur: 0.24, type: 'triangle', gain: 0.28 })
    tone({ freq: 165, dur: 0.2, type: 'sine', gain: 0.2, delay: 0.22, decay: 0.18 })
  },
  combo() {
    const notes = [PENTA.C6, PENTA.E6, PENTA.G6, PENTA.C7]
    notes.forEach((f, i) => {
      tone({ freq: f, dur: 0.18, type: 'triangle', gain: 0.18, delay: i * 0.06, decay: 0.15 })
      tone({ freq: f * 1.5, dur: 0.1, type: 'sine', gain: 0.06, delay: i * 0.06, decay: 0.09 })
    })
    tone({ freq: PENTA.G6, dur: 0.5, type: 'sine', gain: 0.08, delay: 0.3, decay: 0.45 })
  },
  xp() {
    tone({ freq: PENTA.G6, dur: 0.25, type: 'sine', gain: 0.22, decay: 0.22 })
    tone({ freq: PENTA.C7, dur: 0.2, type: 'sine', gain: 0.13, delay: 0.03, decay: 0.17 })
    tone({ freq: PENTA.E6, dur: 0.12, type: 'sine', gain: 0.1, delay: 0.07, decay: 0.1 })
  },
  level_up() {
    const seq = [
      { f: PENTA.C6, t: 0 },
      { f: PENTA.E6, t: 0.1 },
      { f: PENTA.G6, t: 0.2 },
      { f: PENTA.C7, t: 0.3 },
    ]
    seq.forEach(({ f, t }) => {
      tone({ freq: f, dur: 0.18, type: 'triangle', gain: 0.2, delay: t, decay: 0.16 })
      tone({ freq: f * 2, dur: 0.14, type: 'sine', gain: 0.06, delay: t + 0.01, decay: 0.12 })
    })
    tone({ freq: PENTA.C6, dur: 0.7, type: 'triangle', gain: 0.14, delay: 0.45, decay: 0.6 })
    tone({ freq: PENTA.E6, dur: 0.7, type: 'triangle', gain: 0.12, delay: 0.45, decay: 0.6 })
    tone({ freq: PENTA.G6, dur: 0.7, type: 'triangle', gain: 0.1, delay: 0.45, decay: 0.6 })
  },
  legendary() {
    const sparkles = [PENTA.E6, PENTA.G6, PENTA.A6, PENTA.C7, PENTA.E6 * 2]
    sparkles.forEach((f, i) => {
      tone({ freq: f, dur: 0.12, type: 'sine', gain: 0.13, delay: i * 0.05, decay: 0.11 })
    })
    tone({ freq: PENTA.C5, dur: 0.6, type: 'triangle', gain: 0.08, decay: 0.55 })
  },
  achievement() {
    tone({ freq: PENTA.G5, dur: 0.1, type: 'triangle', gain: 0.2, decay: 0.08 })
    tone({ freq: PENTA.C6, dur: 0.3, type: 'sine', gain: 0.15, delay: 0.1, decay: 0.26 })
    tone({ freq: PENTA.E6, dur: 0.3, type: 'sine', gain: 0.12, delay: 0.1, decay: 0.26 })
    noise({ dur: 0.1, gain: 0.06, filterFreq: 6000, filterType: 'highpass', q: 1, delay: 0.1 })
  },
  meal_logged() {
    tone({ freq: PENTA.E6, dur: 0.1, type: 'triangle', gain: 0.16, decay: 0.08 })
    tone({ freq: PENTA.G6, dur: 0.12, type: 'triangle', gain: 0.14, delay: 0.08, decay: 0.1 })
  },
  cycle_logged() {
    // 月曆專屬：柔和雙音 + 一個落下 chime
    tone({ freq: PENTA.D6, dur: 0.14, type: 'sine', gain: 0.18, decay: 0.12 })
    tone({ freq: PENTA.G6, dur: 0.18, type: 'sine', gain: 0.14, delay: 0.08, decay: 0.16 })
    tone({ freq: PENTA.E6, dur: 0.3, type: 'triangle', gain: 0.08, delay: 0.18, decay: 0.28 })
  },
  notify() {
    tone({ freq: PENTA.A5, dur: 0.18, type: 'sine', gain: 0.12, decay: 0.15 })
  },
  box_open() {
    noise({ dur: 0.5, gain: 0.18, filterFreq: 400, filterType: 'lowpass', q: 1 })
    glide({ freqStart: 80, freqEnd: 260, dur: 0.6, type: 'triangle', gain: 0.2 })
    const arp = [PENTA.C5, PENTA.E5, PENTA.G5, PENTA.C6, PENTA.E6]
    arp.forEach((f, i) => {
      tone({ freq: f, dur: 0.32, type: 'sine', gain: 0.2, delay: 0.18 + i * 0.08, decay: 0.28 })
      tone({ freq: f * 2, dur: 0.22, type: 'sine', gain: 0.08, delay: 0.18 + i * 0.08 + 0.01, decay: 0.2 })
    })
    tone({ freq: PENTA.C5, dur: 1.2, type: 'triangle', gain: 0.16, delay: 0.6, decay: 1.0 })
    tone({ freq: PENTA.E5, dur: 1.2, type: 'triangle', gain: 0.13, delay: 0.62, decay: 1.0 })
    tone({ freq: PENTA.G5, dur: 1.2, type: 'triangle', gain: 0.1, delay: 0.64, decay: 1.0 })
  },
  pet() {
    tone({ freq: PENTA.E6, dur: 0.06, type: 'sine', gain: 0.16, decay: 0.05 })
    tone({ freq: PENTA.G6, dur: 0.08, type: 'sine', gain: 0.12, delay: 0.04, decay: 0.06 })
  },
}

// alias
FX.click = FX.ui_tap
FX.open = FX.ui_open
FX.close = FX.ui_close

function play(name: SfxName) {
  if (_muted.value) return
  ensureCtx()
  const fn = FX[name]
  if (!fn) return
  try {
    fn()
  } catch {
    /* swallow */
  }
}

function mute(value: boolean) {
  _muted.value = Boolean(value)
  try {
    localStorage.setItem(LS_KEY, _muted.value ? '1' : '0')
  } catch {
    /* ignore */
  }
}

function toggle(): boolean {
  mute(!_muted.value)
  return _muted.value
}

export function useSfx() {
  return {
    play,
    mute,
    toggle,
    muted: readonly(_muted),
    isMuted: () => _muted.value,
  }
}
