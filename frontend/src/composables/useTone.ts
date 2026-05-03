/**
 * useTone — tone-sensitive 詞彙取詞 helper
 *
 * 依 useInclusiveMode 切換 zh-TW.ts（預設）或 zh-TW-inclusive.ts。
 * key 不存在時 fallback 到 default 字典，再 fallback 到 key 本身。
 *
 * 使用方式（template）：
 *   <p>{{ t('dodo_greeting') }}</p>
 *
 * 使用方式（script）：
 *   const { t } = useTone()
 */
import { computed } from 'vue'
import { useInclusiveMode } from './useInclusiveMode'
import { zhTW, type ToneDict } from '../locales/zh-TW'
import { zhTWInclusive } from '../locales/zh-TW-inclusive'

export function useTone() {
  const inclusive = useInclusiveMode()

  const dict = computed<ToneDict>(() => (inclusive.value ? zhTWInclusive : zhTW))

  function t(key: string): string {
    return dict.value[key] ?? zhTW[key] ?? key
  }

  return { t, dict, inclusive }
}
