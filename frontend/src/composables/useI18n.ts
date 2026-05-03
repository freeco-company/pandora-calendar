/**
 * useI18n — lightweight locale management (no vue-i18n dependency).
 *
 * Why no vue-i18n: adds ~30KB gzipped to bundle. Our needs are simple
 * (key lookup with reactive locale switching), so we hand-roll.
 *
 * Locales:
 *   - 'zh-TW' — primary, full coverage
 *   - 'en'    — early access; missing keys fall back to 'zh-TW'
 *
 * Persistence: localStorage 'locale' = 'zh-TW' | 'en'
 * Auto-detect: if no stored value, detects from navigator.language.
 *   - matches 'zh*' / 'TW' / 'HK' / 'CN' → 'zh-TW'
 *   - else → 'en'
 *
 * Side effect on locale change: sets <html lang="..."> attribute.
 */
import { ref, watch } from 'vue'

export type Locale = 'zh-TW' | 'en'

const KEY = 'locale'

function detectInitial(): Locale {
  try {
    const stored = localStorage.getItem(KEY)
    if (stored === 'zh-TW' || stored === 'en') return stored
  } catch {
    /* private mode etc. */
  }
  try {
    const nav = (navigator.language || 'zh-TW').toLowerCase()
    if (nav.startsWith('zh')) return 'zh-TW'
    return 'en'
  } catch {
    return 'zh-TW'
  }
}

// module-singleton — shared reactive ref across the app
const locale = ref<Locale>(detectInitial())

// Apply <html lang="..."> on init + on every change
function applyHtmlLang(l: Locale) {
  try {
    document.documentElement.lang = l
  } catch {
    /* SSR / non-browser */
  }
}

applyHtmlLang(locale.value)

watch(locale, (val) => {
  try {
    localStorage.setItem(KEY, val)
  } catch {
    /* swallow */
  }
  applyHtmlLang(val)
})

export function useI18n() {
  return {
    locale,
    setLocale(l: Locale) {
      locale.value = l
    },
  }
}

export function getLocale(): Locale {
  return locale.value
}

export function setLocale(l: Locale): void {
  locale.value = l
}
