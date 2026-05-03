// v-sound directive — 一行給任何 element 自動加上點擊音效 + 觸覺回饋
//
// Usage:
//   <button v-sound>OK</button>                 ← 預設 'ui_tap'
//   <button v-sound:tap_select>選</button>       ← arg 指定 SFX 名（會 fallback 到別名 / ui_tap）
//   <div v-sound="'success'" @click="..."/>      ← value 指定（runtime 動態）
//
// 不影響既有 @click handler — capture: false，listener 與 @click 各自獨立 fire。

import type { Directive, DirectiveBinding } from 'vue'
import { useClickSound } from '../composables/useClickSound'

// 別名：把 spec 中要求的「人話 SFX 名」對應到 lib/sound 的 SfxName
const ALIAS: Record<string, string> = {
  tap: 'ui_tap',
  tap_back: 'ui_close',
  tap_select: 'choice_select',
  success: 'correct',
  error: 'wrong',
  swoosh_open: 'ui_open',
  swoosh_close: 'ui_close',
  chime_unlock: 'achievement',
  xp_pop: 'xp',
  levelup_fanfare: 'level_up',
  streak_chime: 'combo',
  confetti: 'legendary',
  notification: 'notify',
  dodo_pop: 'pet',
  swipe: 'card_flip',
}

function resolveName(binding: DirectiveBinding): string {
  const arg = binding.arg as string | undefined
  const val = typeof binding.value === 'string' ? binding.value : null
  const raw = val || arg || 'tap'
  return ALIAS[raw] || raw
}

interface SoundEl extends HTMLElement {
  __soundHandler__?: (ev: Event) => void
}

const handlerMap = new WeakMap<HTMLElement, (ev: Event) => void>()

export const vSound: Directive<SoundEl, string> = {
  mounted(el, binding) {
    const click = useClickSound()
    const handler = () => {
      const name = resolveName(binding)
      click.play(name)
    }
    el.addEventListener('click', handler, { passive: true })
    handlerMap.set(el, handler)
  },
  updated(el, binding) {
    // arg / value 變了就重綁
    if (binding.value === binding.oldValue && binding.arg === (binding as any).oldArg) return
    const old = handlerMap.get(el)
    if (old) el.removeEventListener('click', old)
    const click = useClickSound()
    const handler = () => {
      const name = resolveName(binding)
      click.play(name)
    }
    el.addEventListener('click', handler, { passive: true })
    handlerMap.set(el, handler)
  },
  unmounted(el) {
    const old = handlerMap.get(el)
    if (old) el.removeEventListener('click', old)
    handlerMap.delete(el)
  },
}

export default vSound
