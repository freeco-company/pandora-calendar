<script setup lang="ts">
/**
 * TrialStartedToast — onboarding 完成後一次性 fullscreen 慶祝 modal。
 * Trigger: localStorage `pandora_calendar_trial_started_toast_pending` = '1'
 *          且 shown flag 未設。顯示後立即 mark shown。
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Character from './Character.vue'
import { useTone } from '../composables/useTone'

const SHOWN_KEY = 'pandora_calendar_trial_started_toast_shown'
const PENDING_KEY = 'pandora_calendar_trial_started_toast_pending'

const router = useRouter()
const { t } = useTone()
const visible = ref(false)

onMounted(() => {
  try {
    const shown = localStorage.getItem(SHOWN_KEY) === '1'
    const pending = localStorage.getItem(PENDING_KEY) === '1'
    if (!shown && pending) {
      visible.value = true
      localStorage.setItem(SHOWN_KEY, '1')
      localStorage.removeItem(PENDING_KEY)
    }
  } catch { /* private mode */ }
})

function close() { visible.value = false }
function startTracking() {
  visible.value = false
  router.push('/log').catch(() => {})
}
</script>

<template>
  <Transition name="trial-toast">
    <div
      v-if="visible"
      class="fixed inset-0 z-[80] bg-stone-900/30 backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
      data-test="trial-started-toast"
      @click.self="close"
    >
      <div
        class="w-full max-w-sm bg-gradient-to-br from-peach-50 via-cream-50 to-sakura-50 rounded-3xl p-6 shadow-soft-lg space-y-4 trial-toast-pop relative overflow-hidden"
      >
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-50 trial-toast-sparkle-bg" />
        <button
          type="button"
          class="absolute top-3 right-3 w-8 h-8 rounded-full text-stone-400 hover:text-stone-600 hover:bg-white/60 flex items-center justify-center text-xl leading-none z-10"
          :aria-label="t('trial_started_close_aria')"
          @click="close"
          data-test="trial-started-toast-close"
        >×</button>

        <header class="relative z-10 flex flex-col items-center text-center space-y-3">
          <Character species="dodo" mood="cheering" :size="120" :show-halo="true" :floaty="true" />
          <p class="font-zen text-[11px] text-peach-500/80 tracking-[0.3em] uppercase">
            {{ t('trial_started_eyebrow') }}
          </p>
          <h2 class="font-display text-[22px] font-black text-peach-500 leading-tight px-2">
            {{ t('trial_started_headline') }}
          </h2>
          <p class="font-zen text-sm text-stone-600 leading-relaxed px-2">
            {{ t('trial_started_subtitle') }}
          </p>
        </header>

        <div class="relative z-10 bg-white/70 rounded-2xl p-3 space-y-1.5">
          <p class="font-zen text-[12px] text-stone-600 leading-relaxed">
            {{ t('trial_started_promise') }}
          </p>
        </div>

        <button
          type="button"
          class="relative z-10 w-full py-3.5 rounded-2xl bg-peach-gradient text-white font-display font-black text-base shadow-lg active:scale-[0.99] transition-transform"
          data-test="trial-started-toast-cta"
          @click="startTracking"
        >
          {{ t('trial_started_cta') }}
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.trial-toast-enter-active,
.trial-toast-leave-active { transition: opacity 0.25s ease; }
.trial-toast-enter-from,
.trial-toast-leave-to { opacity: 0; }
.trial-toast-pop {
  animation: trial-toast-pop 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes trial-toast-pop {
  from { transform: translateY(20px) scale(0.95); opacity: 0; }
  to   { transform: translateY(0) scale(1); opacity: 1; }
}
.trial-toast-sparkle-bg {
  background-image:
    radial-gradient(circle at 20% 20%, rgba(255, 200, 180, 0.5) 0%, transparent 40%),
    radial-gradient(circle at 80% 30%, rgba(255, 220, 230, 0.5) 0%, transparent 40%),
    radial-gradient(circle at 50% 90%, rgba(255, 240, 220, 0.5) 0%, transparent 40%);
  animation: trial-toast-bg-shift 6s ease-in-out infinite;
}
@keyframes trial-toast-bg-shift {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
@media (prefers-reduced-motion: reduce) {
  .trial-toast-pop, .trial-toast-sparkle-bg { animation: none; }
}
</style>
