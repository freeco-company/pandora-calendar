<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { YearReviewApi, PaywallRequiredError, type YearReviewCard } from '../api'
import { Capacitor } from '@capacitor/core'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Button from '../components/ui/Button.vue'
import { useSfx } from '../lib/sound'
import { useTone } from '../composables/useTone'

const route = useRoute()
const router = useRouter()
const sfx = useSfx()
const { t } = useTone()

const year = computed(() => {
  const raw = route.params.year as string | undefined
  const parsed = raw ? Number(raw) : new Date().getFullYear()
  return Number.isFinite(parsed) ? parsed : new Date().getFullYear()
})

const loading = ref(true)
const error = ref<string | null>(null)
const insufficient = ref(false)
const cards = ref<YearReviewCard[]>([])
const idx = ref(0)

const current = computed<YearReviewCard | null>(() => cards.value[idx.value] ?? null)
const isLast = computed(() => idx.value >= cards.value.length - 1)

async function load() {
  loading.value = true
  error.value = null
  insufficient.value = false
  try {
    const res = await YearReviewApi.show(year.value)
    const data = res.data.data
    if (data.insufficient || !data.cards || data.cards.length === 0) {
      insufficient.value = true
      cards.value = []
    } else {
      cards.value = [...data.cards].sort((a, b) => a.sort - b.sort)
      idx.value = 0
    }
  } catch (e) {
    if (e instanceof PaywallRequiredError) {
      router.push(e.paywallRedirect || '/me/premium')
      return
    }
    error.value = t('year_review_load_failed')
  } finally {
    loading.value = false
  }
}

function next() {
  if (isLast.value) return
  sfx.play('ui_tap')
  idx.value += 1
}

function prev() {
  if (idx.value === 0) return
  sfx.play('ui_tap')
  idx.value -= 1
}

function restart() {
  sfx.play('ui_open')
  idx.value = 0
}

async function share() {
  sfx.play('correct')
  const text = t('year_review_share_text', { year: year.value, title: current.value?.title ?? '' })
  const url = window.location.href
  try {
    if (navigator.share) {
      await navigator.share({ title: t('year_review_share_title'), text, url })
      return
    }
    if (Capacitor.isNativePlatform()) {
      // Capacitor Share plugin（若已安裝；用 dynamic specifier 跳過 TS resolve）
      try {
        const specifier = '@capacitor/share'
        const mod: any = await import(/* @vite-ignore */ specifier)
        await mod.Share.share({ title: t('year_review_share_title'), text, url })
        return
      } catch {
        /* plugin 未裝，fallback */
      }
    }
    await navigator.clipboard.writeText(`${text}\n${url}`)
    alert(t('year_review_copied'))
  } catch {
    /* 用戶取消 share，靜默 */
  }
}

function onKey(ev: KeyboardEvent) {
  if (ev.key === 'ArrowRight' || ev.key === ' ') next()
  if (ev.key === 'ArrowLeft') prev()
}

let touchStartX = 0
function onTouchStart(ev: TouchEvent) {
  touchStartX = ev.touches[0]?.clientX ?? 0
}
function onTouchEnd(ev: TouchEvent) {
  const endX = ev.changedTouches[0]?.clientX ?? touchStartX
  const dx = endX - touchStartX
  if (Math.abs(dx) < 40) return
  if (dx < 0) next()
  else prev()
}

onMounted(() => {
  load()
  window.addEventListener('keydown', onKey)
})
</script>

<template>
  <div
    class="fixed inset-0 z-40 bg-gradient-to-br from-peach-50 via-sakura-50 to-cream-50 overflow-hidden"
    @touchstart="onTouchStart"
    @touchend="onTouchEnd"
  >
    <button
      class="absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white/80 backdrop-blur shadow-soft flex items-center justify-center text-stone-500"
      style="top: calc(env(safe-area-inset-top) + 1rem)"
      :aria-label="t('year_review_close')"
      @click="router.back()"
    >
      ✕
    </button>

    <div class="absolute top-4 left-1/2 -translate-x-1/2 z-10" style="top: calc(env(safe-area-inset-top) + 1rem)">
      <div class="px-4 py-1.5 rounded-full bg-white/80 backdrop-blur shadow-soft">
        <p class="font-display text-xs font-bold text-peach-500">{{ t('year_review_year_pill', { year }) }}</p>
      </div>
    </div>

    <!-- progress bars -->
    <div
      v-if="cards.length"
      class="absolute left-4 right-4 flex gap-1 z-10"
      style="top: calc(env(safe-area-inset-top) + 4rem)"
    >
      <div
        v-for="(_, i) in cards"
        :key="i"
        class="h-1 rounded-full flex-1 transition-colors"
        :class="i <= idx ? 'bg-peach-400' : 'bg-white/60'"
      />
    </div>

    <div class="absolute inset-0 flex items-center justify-center px-6">
      <Spinner v-if="loading" :label="t('year_review_loading')" size="lg" />

      <div v-else-if="error" class="text-center space-y-4 max-w-sm">
        <EmptyState icon="🌸" :title="error" :subtitle="t('year_review_load_retry_subtitle')" />
        <Button @click="load">{{ t('common_retry_short') }}</Button>
      </div>

      <div v-else-if="insufficient" class="text-center space-y-4 max-w-sm">
        <EmptyState
          show-dodo
          :title="t('year_review_insufficient_title')"
          :subtitle="t('year_review_insufficient_subtitle')"
        />
        <Button variant="secondary" @click="router.push('/calendar')">{{ t('year_review_back_to_calendar') }}</Button>
      </div>

      <div
        v-else-if="current"
        :key="current.id"
        class="w-full max-w-sm text-center space-y-6 animate-fadein"
      >
        <div class="text-7xl leading-none">{{ current.emoji }}</div>
        <h1 class="font-display text-3xl font-bold text-peach-500 leading-tight px-2">
          {{ current.title }}
        </h1>
        <p class="font-zen text-base text-stone-700 leading-relaxed whitespace-pre-line px-3">
          {{ current.body }}
        </p>
        <p
          v-if="current.subtitle"
          class="font-zen text-sm text-stone-500 leading-relaxed px-4"
        >
          {{ current.subtitle }}
        </p>
      </div>
    </div>

    <!-- bottom CTAs（thumb-zone 友善） -->
    <div
      v-if="!loading && !error && cards.length"
      class="absolute bottom-0 left-0 right-0 px-6 pb-6 pt-4 bg-gradient-to-t from-white/90 to-transparent"
      style="padding-bottom: calc(env(safe-area-inset-bottom) + 1.5rem)"
    >
      <div v-if="!isLast" class="flex gap-3 max-w-sm mx-auto">
        <Button v-if="idx > 0" variant="secondary" @click="prev">{{ t('year_review_prev') }}</Button>
        <Button full @click="next">{{ t('year_review_next') }}</Button>
      </div>
      <div v-else class="flex flex-col gap-2 max-w-sm mx-auto">
        <Button full @click="share">{{ t('year_review_share') }}</Button>
        <Button variant="ghost" full @click="restart">{{ t('year_review_replay') }}</Button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fadein {
  animation: fadein 0.45s ease-out;
}
@keyframes fadein {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
