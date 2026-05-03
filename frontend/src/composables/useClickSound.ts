// useClickSound — 統一播音 + 觸覺回饋 composable
//
// 集中處理：
//   - SFX 播放（透過 lib/sound）
//   - Capacitor Haptics（native iOS / Android）/ navigator.vibrate fallback
//   - debounce 防 spam（同 sound 50ms 內忽略）
//   - haptic on/off 獨立於 sound on/off（localStorage `pandora_calendar_haptic_disabled`）

import { ref, readonly } from 'vue'
import { useSfx, type SfxName } from '../lib/sound'

const HAPTIC_LS_KEY = 'pandora_calendar_haptic_disabled'

const _hapticDisabled = ref<boolean>(
  typeof localStorage !== 'undefined' && localStorage.getItem(HAPTIC_LS_KEY) === '1',
)

const _lastPlay: Record<string, number> = {}
const SPAM_GUARD_MS = 50

let _hapticsImpl: ((style: 'light' | 'medium' | 'heavy') => void) | null = null
let _hapticsLoading = false

async function loadHaptics() {
  if (_hapticsImpl || _hapticsLoading) return
  _hapticsLoading = true
  try {
    // dynamic import 避免 web build 強制依賴
    const mod: any = await import('@capacitor/haptics').catch(() => null)
    if (mod?.Haptics && mod?.ImpactStyle) {
      const { Haptics, ImpactStyle } = mod
      _hapticsImpl = (style) => {
        const map = {
          light: ImpactStyle.Light,
          medium: ImpactStyle.Medium,
          heavy: ImpactStyle.Heavy,
        }
        try {
          Haptics.impact({ style: map[style] })
        } catch {
          /* swallow */
        }
      }
    }
  } finally {
    _hapticsLoading = false
  }
}

if (typeof window !== 'undefined') {
  // 第一次 user gesture 後再載 haptics（與 audio unlock 同節奏）
  const trigger = () => {
    loadHaptics()
    window.removeEventListener('pointerdown', trigger)
    window.removeEventListener('touchstart', trigger)
  }
  window.addEventListener('pointerdown', trigger, { once: true })
  window.addEventListener('touchstart', trigger, { once: true, passive: true })
}

function vibrate(style: 'light' | 'medium' | 'heavy') {
  if (_hapticDisabled.value) return
  if (_hapticsImpl) {
    _hapticsImpl(style)
    return
  }
  // web fallback
  if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
    const ms = style === 'heavy' ? 25 : style === 'medium' ? 15 : 8
    try {
      navigator.vibrate(ms)
    } catch {
      /* swallow */
    }
  }
}

function setHapticDisabled(disabled: boolean) {
  _hapticDisabled.value = Boolean(disabled)
  try {
    localStorage.setItem(HAPTIC_LS_KEY, _hapticDisabled.value ? '1' : '0')
  } catch {
    /* ignore */
  }
}

function toggleHaptic(): boolean {
  setHapticDisabled(!_hapticDisabled.value)
  return _hapticDisabled.value
}

export function useClickSound() {
  const sfx = useSfx()

  function play(name: SfxName | string, opts?: { haptic?: 'light' | 'medium' | 'heavy' | false }) {
    const now = Date.now()
    const key = String(name)
    if (_lastPlay[key] && now - _lastPlay[key] < SPAM_GUARD_MS) return
    _lastPlay[key] = now
    sfx.play(name as SfxName)
    const hStyle = opts?.haptic === undefined ? 'light' : opts.haptic
    if (hStyle) vibrate(hStyle)
  }

  return {
    play,
    vibrate,
    sfx,
    hapticDisabled: readonly(_hapticDisabled),
    isHapticDisabled: () => _hapticDisabled.value,
    setHapticDisabled,
    toggleHaptic,
  }
}
