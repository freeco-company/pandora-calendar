/**
 * useTone — tone- and locale-sensitive 詞彙取詞 helper
 *
 * 兩個維度：
 *   1. locale:    'zh-TW' | 'en'           (useI18n)
 *   2. inclusive: false | true             (useInclusiveMode)
 *
 * → 4 dictionaries:
 *   - zh-TW × normal     → zhTW
 *   - zh-TW × inclusive  → zhTWInclusive
 *   - en    × normal     → en
 *   - en    × inclusive  → enInclusive
 *
 * Lookup chain (key not found → fall back):
 *   active dict → zh-TW dict (canonical) → key itself
 *
 * 使用方式（template）：
 *   <p>{{ t('dodo_greeting') }}</p>
 *
 * 使用方式（script）：
 *   const { t } = useTone()
 */
import { computed } from 'vue'
import { useInclusiveMode } from './useInclusiveMode'
import { useI18n } from './useI18n'
import { zhTW, type ToneDict } from '../locales/zh-TW'
import { zhTWInclusive } from '../locales/zh-TW-inclusive'
import { en } from '../locales/en'
import { enInclusive } from '../locales/en-inclusive'

export function useTone() {
  const inclusive = useInclusiveMode()
  const { locale } = useI18n()

  const dict = computed<ToneDict>(() => {
    if (locale.value === 'en') {
      return inclusive.value ? enInclusive : en
    }
    return inclusive.value ? zhTWInclusive : zhTW
  })

  function t(key: string, params?: Record<string, string | number>): string {
    const raw = dict.value[key] ?? zhTW[key] ?? key
    if (!params) return raw
    return Object.keys(params).reduce(
      (acc, k) => acc.replace(new RegExp(`\\{\\{\\s*${k}\\s*\\}\\}`, 'g'), String(params[k])),
      raw,
    )
  }

  return { t, dict, inclusive, locale }
}
