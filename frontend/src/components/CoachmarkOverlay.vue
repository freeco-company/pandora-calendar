<script setup lang="ts">
/**
 * CoachmarkOverlay — spotlight + 朵朵 dialog bubble for onboarding tours.
 *
 * Mounted once at App.vue level. Reads state from useOnboardingTour() singleton
 * so any view that calls startIfNew() will pop this overlay automatically.
 *
 * UX:
 *   - semi-transparent backdrop (z-100) over everything
 *   - spotlight = box-shadow inset trick to "cut out" target rect with padding
 *   - 朵朵 emoji + bubble: title + body + step counter + 3 actions
 *   - actions: skip(全跳過) / prev(if not first) / next(下一步 / 完成)
 *   - ESC key cancels
 *   - prefers-reduced-motion → no transition
 *   - if target not found, render fullscreen mode (centered bubble, no spotlight)
 */
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useOnboardingTour } from '../composables/useOnboardingTour'
import { useTone } from '../composables/useTone'

const tour = useOnboardingTour()
const { t } = useTone()

const targetRect = ref<DOMRect | null>(null)
const reducedMotion = ref(false)

function detectReducedMotion() {
  try {
    reducedMotion.value =
      typeof window !== 'undefined' &&
      window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
  } catch {
    reducedMotion.value = false
  }
}

async function measureTarget() {
  await nextTick()
  const step = tour.currentStep.value
  if (!step) {
    targetRect.value = null
    return
  }
  if (step.fullscreen) {
    targetRect.value = null
    return
  }
  try {
    const el = document.querySelector(step.target) as HTMLElement | null
    if (!el) {
      // target not on page → degrade to fullscreen-style centered bubble
      targetRect.value = null
      return
    }
    el.scrollIntoView({ behavior: reducedMotion.value ? 'auto' : 'smooth', block: 'center' })
    // re-measure after scroll settles
    setTimeout(() => {
      try {
        targetRect.value = el.getBoundingClientRect()
      } catch {
        targetRect.value = null
      }
    }, reducedMotion.value ? 0 : 280)
  } catch {
    targetRect.value = null
  }
}

watch(
  () => [tour.active.value, tour.stepIndex.value] as const,
  () => {
    measureTarget()
  },
  { immediate: true },
)

function onKey(e: KeyboardEvent) {
  if (!tour.active.value) return
  if (e.key === 'Escape') {
    e.preventDefault()
    tour.skip()
  } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
    e.preventDefault()
    tour.next()
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    tour.prev()
  }
}

function onResize() {
  if (tour.active.value) measureTarget()
}

onMounted(() => {
  detectReducedMotion()
  document.addEventListener('keydown', onKey)
  window.addEventListener('resize', onResize)
  window.addEventListener('scroll', onResize, { passive: true })
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKey)
  window.removeEventListener('resize', onResize)
  window.removeEventListener('scroll', onResize)
})

// === geometry ===
const SPOTLIGHT_PADDING = 8
const BUBBLE_GAP = 16

const spotlightStyle = computed(() => {
  const r = targetRect.value
  if (!r) return null
  return {
    top: `${r.top - SPOTLIGHT_PADDING}px`,
    left: `${r.left - SPOTLIGHT_PADDING}px`,
    width: `${r.width + SPOTLIGHT_PADDING * 2}px`,
    height: `${r.height + SPOTLIGHT_PADDING * 2}px`,
  }
})

/**
 * Bubble position — near the target, flipped if too close to viewport edge.
 * Falls back to centered when no target.
 */
const bubbleStyle = computed(() => {
  const r = targetRect.value
  const step = tour.currentStep.value
  if (!r || !step) {
    return {
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      maxWidth: 'min(360px, calc(100vw - 32px))',
    }
  }
  const placement = step.placement ?? 'bottom'
  const vh = window.innerHeight
  const vw = window.innerWidth
  const bubbleW = Math.min(360, vw - 32)
  const estimatedH = 200
  let top = 0
  let left = Math.max(16, Math.min(vw - bubbleW - 16, r.left + r.width / 2 - bubbleW / 2))

  if (placement === 'top') {
    top = r.top - estimatedH - BUBBLE_GAP
    if (top < 16) top = r.bottom + BUBBLE_GAP // flip
  } else if (placement === 'bottom') {
    top = r.bottom + BUBBLE_GAP
    if (top + estimatedH > vh - 16) top = Math.max(16, r.top - estimatedH - BUBBLE_GAP)
  } else {
    top = r.bottom + BUBBLE_GAP
  }
  return {
    top: `${top}px`,
    left: `${left}px`,
    maxWidth: `${bubbleW}px`,
  }
})

const isFirstStep = computed(() => tour.stepIndex.value === 0)
const isLastStep = computed(() => tour.stepIndex.value === tour.totalSteps.value - 1)
const progressLabel = computed(() =>
  t('tour_step_progress', {
    n: tour.stepIndex.value + 1,
    total: tour.totalSteps.value,
  }),
)
</script>

<template>
  <Teleport to="body">
    <Transition name="tour-fade">
      <div
        v-if="tour.active.value && tour.currentStep.value"
        class="fixed inset-0 z-[100] pointer-events-auto"
        role="dialog"
        aria-modal="true"
        :aria-label="t('tour_aria_label')"
        data-test="coachmark-overlay"
      >
        <!-- Backdrop with cut-out spotlight (box-shadow trick) -->
        <div
          v-if="spotlightStyle"
          class="absolute rounded-2xl pointer-events-none"
          :class="reducedMotion ? '' : 'transition-all duration-300 ease-out'"
          :style="{
            ...spotlightStyle,
            boxShadow: '0 0 0 9999px rgba(15, 12, 24, 0.55)',
            outline: '2px solid rgba(255,255,255,0.7)',
            outlineOffset: '2px',
          }"
          data-test="coachmark-spotlight"
        />
        <!-- Full backdrop fallback when no target -->
        <div
          v-else
          class="absolute inset-0 bg-stone-900/55"
          data-test="coachmark-backdrop"
        />

        <!-- Bubble -->
        <div
          class="absolute bg-white rounded-3xl shadow-2xl p-5 space-y-3 font-zen"
          :class="reducedMotion ? '' : 'transition-all duration-300 ease-out'"
          :style="bubbleStyle"
          data-test="coachmark-bubble"
        >
          <header class="flex items-start gap-3">
            <span class="text-3xl leading-none shrink-0" aria-hidden="true">🌸</span>
            <div class="flex-1 min-w-0">
              <p class="font-display text-base font-bold text-peach-500 leading-snug">
                {{ t(tour.currentStep.value.titleKey) }}
              </p>
              <p class="text-[11px] text-stone-400 mt-0.5">{{ progressLabel }}</p>
            </div>
            <button
              type="button"
              class="shrink-0 -mt-1 -mr-1 p-1 text-stone-400 hover:text-stone-600 active:scale-95 transition-transform"
              :aria-label="t('tour_btn_close')"
              data-test="coachmark-close"
              @click="tour.skip()"
            >✕</button>
          </header>

          <p class="text-sm text-stone-700 leading-relaxed">
            {{ t(tour.currentStep.value.bodyKey) }}
          </p>

          <!-- Progress dots -->
          <div class="flex items-center justify-center gap-1.5 pt-1">
            <span
              v-for="i in tour.totalSteps.value"
              :key="i"
              class="w-1.5 h-1.5 rounded-full"
              :class="i - 1 === tour.stepIndex.value ? 'bg-peach-500 w-4' : 'bg-stone-200'"
              :style="reducedMotion ? '' : 'transition: all 0.3s'"
              aria-hidden="true"
            />
          </div>

          <footer class="flex items-center justify-between gap-2 pt-1">
            <button
              type="button"
              class="text-[11px] text-stone-400 hover:text-stone-600 px-2 py-1.5 active:scale-95 transition-transform"
              data-test="coachmark-skip"
              @click="tour.skip()"
            >{{ t('tour_btn_skip') }}</button>

            <div class="flex items-center gap-2">
              <button
                v-if="!isFirstStep"
                type="button"
                class="text-[12px] text-peach-500 hover:text-peach-600 px-3 py-1.5 rounded-full active:scale-95 transition-transform"
                data-test="coachmark-prev"
                @click="tour.prev()"
              >{{ t('tour_btn_prev') }}</button>
              <button
                type="button"
                class="bg-peach-500 hover:bg-peach-600 text-white text-[12px] font-bold rounded-full px-4 py-2 active:scale-95 transition-all"
                data-test="coachmark-next"
                @click="tour.next()"
              >
                {{ isLastStep ? t('tour_btn_done') : t('tour_btn_next') }}
              </button>
            </div>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.tour-fade-enter-active,
.tour-fade-leave-active {
  transition: opacity 0.25s ease;
}
.tour-fade-enter-from,
.tour-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .tour-fade-enter-active,
  .tour-fade-leave-active {
    transition: none;
  }
}
</style>
