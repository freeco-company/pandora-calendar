/**
 * useInclusiveMode — 跨性別 / 非二元友善 toggle
 *
 * 預設 false（沿用集團 tone-of-voice 為主，不擅自改）。
 * 使用 reactive ref 讓 view 即時切換，無需重新整理。
 *
 * 與 useI18n 正交（兩個獨立維度）：
 *   locale ('zh-TW' | 'en') × inclusive (false | true) → 4 dictionaries
 *   實際 dispatch 在 useTone() 裡組合。
 *
 * 持久化：localStorage `inclusive_mode` = '1' / '0'
 */
import { ref, watch } from 'vue'

const KEY = 'inclusive_mode'

function readInitial(): boolean {
  try {
    return localStorage.getItem(KEY) === '1'
  } catch {
    return false
  }
}

// module-singleton — 全 App 共享同一個 reactive ref
const inclusiveMode = ref<boolean>(readInitial())

watch(inclusiveMode, (val) => {
  try {
    localStorage.setItem(KEY, val ? '1' : '0')
  } catch {
    /* private mode 等情境，吞掉 */
  }
})

export function useInclusiveMode() {
  return inclusiveMode
}

export function isInclusiveMode(): boolean {
  return inclusiveMode.value
}

export function setInclusiveMode(val: boolean): void {
  inclusiveMode.value = val
}
