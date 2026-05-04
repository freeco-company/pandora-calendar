<script setup lang="ts">
import { computed } from 'vue'
import { useStreakToast } from '../composables/useStreakToast'

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — daily login streak toast.
 *
 * Mount 一次（App.vue），由 useStreakToast() composable 控制顯示。
 *
 * 行為：
 *   - is_first_today=true 普通日：3s slide-down「連續第 N 天 🔥」
 *   - is_milestone=true 里程碑日：5s + 朵朵頭像 + 導師語氣文案 + reveal unlocks
 *   - 21 / 100 天 fullscreen overlay variant（tap dismiss）
 *
 * 文案紅線（集團硬規則）：
 *   - 用「妳 / 你 / 朋友 / 夥伴」，不寫「您 / 會員 / 用戶」
 *   - 不寫「集團」「公司」
 *   - 朵朵 = 導師角色（給建議與提醒），文案口吻溫柔但不做療效宣稱
 *   - 不出現減脂 / 燃脂 / 排毒 / 治療等紅線詞
 */

const { state, visible, dismiss, isFullscreenMilestone } = useStreakToast()

const streak = computed(() => state.value?.current_streak ?? 0)
const isMilestone = computed(() => !!state.value?.is_milestone)
const isFullscreen = computed(
  () => isMilestone.value && isFullscreenMilestone(streak.value),
)

const milestoneMessage = computed(() => {
  if (!state.value?.is_milestone) return ''
  const n = state.value.current_streak
  return `妳已經連續 ${n} 天了，朋友！`
})

const unlocks = computed(() => state.value?.unlocks ?? null)

const hasOutfit = computed(() => !!unlocks.value?.outfit_unlocked)
const hasXpBonus = computed(() => (unlocks.value?.xp_bonus ?? 0) > 0)
const hasCards = computed(() => (unlocks.value?.cards_unlocked?.length ?? 0) > 0)
const hasUnlocks = computed(
  () => hasOutfit.value || hasXpBonus.value || hasCards.value,
)

/**
 * Outfit display name lookup — kept inline (not imported from a shared
 * catalog) so toast renders even if catalog code-split chunk is delayed.
 * Drift here is acceptable: backend gives us the code, fallback shows code.
 */
const OUTFIT_NAMES: Record<string, string> = {
  sparkle_pin: '閃亮髮夾',
  sakura: '櫻花瓣',
  star_clip: '星星髮夾',
  starry_cape: '星光斗篷',
  moon_tiara: '月光冠冕',
  angel_wings: '天使翅膀',
}

const outfitName = computed(() => {
  const code = unlocks.value?.outfit_unlocked
  if (!code) return null
  return OUTFIT_NAMES[code] ?? code
})
</script>

<template>
  <Transition :name="isFullscreen ? 'streak-overlay' : 'streak-fade'">
    <!-- ═══════════ Fullscreen overlay variant (21 / 100 天) ═══════════ -->
    <div
      v-if="visible && state && state.is_first_today && isFullscreen"
      class="streak-overlay fixed inset-0 z-[80] flex items-center justify-center pointer-events-auto"
      role="dialog"
      aria-modal="true"
      aria-live="polite"
      data-test="streak-toast"
      data-variant="fullscreen"
      @click="dismiss"
    >
      <!-- backdrop -->
      <div class="absolute inset-0 bg-gradient-to-br from-peach-500/85 to-sakura-500/85 backdrop-blur-sm" />

      <!-- floating card -->
      <div
        class="relative max-w-sm mx-6 px-6 py-7 rounded-3xl bg-white/95 ring-2 ring-white/60 shadow-2xl text-center font-zen"
      >
        <!-- 朵朵 anchor 大圖 -->
        <div class="flex justify-center mb-3">
          <span
            aria-hidden="true"
            class="inline-flex w-20 h-20 rounded-full bg-gradient-to-br from-peach-100 to-sakura-200 ring-4 ring-white/80 items-center justify-center shadow-md"
          >
            <svg viewBox="0 0 64 64" width="64" height="64" fill="none">
              <ellipse cx="32" cy="40" rx="20" ry="18" fill="#FCE6D6" />
              <ellipse cx="32" cy="26" rx="16" ry="15" fill="#FCE6D6" />
              <path
                d="M16 22 Q32 6 48 22 Q44 16 32 14 Q20 16 16 22 Z"
                fill="#FFB8A8"
              />
              <ellipse cx="26" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
              <ellipse cx="38" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
              <circle cx="22" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
              <circle cx="42" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
              <path d="M28 33 Q32 36 36 33" stroke="#5C3A2E" stroke-width="1.4" stroke-linecap="round" fill="none" />
            </svg>
          </span>
        </div>

        <p class="text-xl font-bold text-stone-700 mb-1">
          {{ state.milestone_label || `連續 ${streak} 天` }} 🌸
        </p>
        <p class="text-sm text-stone-600 mb-4">{{ milestoneMessage }}</p>

        <!-- Unlock reveal stack -->
        <div v-if="hasUnlocks" class="space-y-2 text-sm" data-test="streak-unlocks">
          <div
            v-if="hasOutfit"
            class="streak-reveal-item flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-peach-50 text-stone-700"
            data-test="unlock-outfit"
          >
            <span aria-hidden="true">✨</span>
            <span>解鎖新裝扮：<b>{{ outfitName }}</b></span>
          </div>
          <div
            v-if="hasXpBonus"
            class="streak-reveal-item flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-sakura-50 text-stone-700"
            data-test="unlock-xp"
          >
            <span aria-hidden="true">⭐</span>
            <span>+{{ unlocks?.xp_bonus }} 經驗值</span>
          </div>
          <div
            v-for="card in unlocks?.cards_unlocked ?? []"
            :key="card.code"
            class="streak-reveal-item flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-cream-100 text-stone-700"
            data-test="unlock-card"
          >
            <span aria-hidden="true">🏅</span>
            <span>{{ card.label }}</span>
          </div>
        </div>

        <p class="text-xs text-stone-400 mt-4">點任意處關閉</p>
      </div>
    </div>

    <!-- ═══════════ Standard slide-down toast (普通 + 一般里程碑) ═══════════ -->
    <div
      v-else-if="visible && state && state.is_first_today"
      class="streak-toast fixed top-4 left-1/2 -translate-x-1/2 z-[70] pointer-events-auto"
      :class="{ 'streak-milestone': isMilestone }"
      style="padding-top: env(safe-area-inset-top)"
      role="status"
      aria-live="polite"
      data-test="streak-toast"
      :data-variant="isMilestone ? 'milestone' : 'daily'"
      @click="dismiss"
    >
      <div
        class="flex flex-col gap-2 px-4 py-3 rounded-2xl shadow-lg ring-2 ring-white/40"
        :class="
          isMilestone
            ? 'bg-gradient-to-br from-peach-500 to-sakura-500 text-white min-w-[260px]'
            : 'bg-gradient-to-br from-cream-100 to-peach-100 text-stone-700 min-w-[200px]'
        "
      >
        <div class="flex items-center gap-3">
          <!-- Milestone：朵朵頭像（inline SVG，療癒手繪 anchor 風）-->
          <span
            v-if="isMilestone"
            aria-hidden="true"
            class="inline-flex shrink-0 w-10 h-10 rounded-full bg-white/30 ring-2 ring-white/60 items-center justify-center"
          >
            <svg viewBox="0 0 64 64" width="32" height="32" fill="none">
              <ellipse cx="32" cy="40" rx="20" ry="18" fill="#FCE6D6" />
              <ellipse cx="32" cy="26" rx="16" ry="15" fill="#FCE6D6" />
              <path
                d="M16 22 Q32 6 48 22 Q44 16 32 14 Q20 16 16 22 Z"
                fill="#FFB8A8"
              />
              <ellipse cx="26" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
              <ellipse cx="38" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
              <circle cx="22" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
              <circle cx="42" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
              <path d="M28 33 Q32 36 36 33" stroke="#5C3A2E" stroke-width="1.4" stroke-linecap="round" fill="none" />
            </svg>
          </span>

          <span v-else aria-hidden="true" class="text-2xl">🔥</span>

          <div class="leading-tight font-zen flex-1">
            <p class="font-bold text-base">
              <template v-if="isMilestone">
                {{ state.milestone_label || `連續 ${streak} 天` }} 🌸
              </template>
              <template v-else>連續第 {{ streak }} 天 🔥</template>
            </p>
            <p
              v-if="isMilestone"
              class="text-sm opacity-95 mt-0.5"
              style="text-shadow: 0 1px 2px rgba(80,40,30,0.3)"
            >
              {{ milestoneMessage }}
            </p>
            <p v-else class="text-xs opacity-80">朵朵看見妳今天又來了</p>
          </div>
        </div>

        <!-- Inline unlocks reveal for non-fullscreen milestones -->
        <div
          v-if="isMilestone && hasUnlocks"
          class="flex flex-col gap-1 text-xs font-zen"
          data-test="streak-unlocks"
        >
          <div
            v-if="hasOutfit"
            class="streak-reveal-item flex items-center gap-2 px-2 py-1.5 rounded-lg bg-white/30 text-white"
            data-test="unlock-outfit"
          >
            <span aria-hidden="true">✨</span>
            <span>解鎖裝扮：<b>{{ outfitName }}</b></span>
          </div>
          <div
            v-if="hasXpBonus"
            class="streak-reveal-item flex items-center gap-2 px-2 py-1.5 rounded-lg bg-white/30 text-white"
            data-test="unlock-xp"
          >
            <span aria-hidden="true">⭐</span>
            <span>+{{ unlocks?.xp_bonus }} 經驗值</span>
          </div>
          <div
            v-for="card in unlocks?.cards_unlocked ?? []"
            :key="card.code"
            class="streak-reveal-item flex items-center gap-2 px-2 py-1.5 rounded-lg bg-white/20 text-white/95"
            data-test="unlock-card"
          >
            <span aria-hidden="true">🏅</span>
            <span>{{ card.label }}</span>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.streak-fade-enter-active,
.streak-fade-leave-active {
  transition: all 320ms cubic-bezier(0.4, 0, 0.2, 1);
}
.streak-fade-enter-from {
  opacity: 0;
  transform: translate(-50%, -16px) scale(0.95);
}
.streak-fade-leave-to {
  opacity: 0;
  transform: translate(-50%, -8px) scale(0.97);
}

/* Fullscreen overlay fade + scale */
.streak-overlay-enter-active,
.streak-overlay-leave-active {
  transition: opacity 280ms ease-out;
}
.streak-overlay-enter-from,
.streak-overlay-leave-to {
  opacity: 0;
}

/* Milestone 額外 sparkle bounce */
.streak-milestone {
  animation: streak-bounce 600ms cubic-bezier(0.34, 1.56, 0.64, 1) 1;
}

@keyframes streak-bounce {
  0% { transform: translateX(-50%) scale(0.8); }
  60% { transform: translateX(-50%) scale(1.06); }
  100% { transform: translateX(-50%) scale(1); }
}

/* Reveal items appear staggered for that "ta-da" feeling */
.streak-reveal-item {
  animation: streak-reveal 480ms cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
}
.streak-reveal-item:nth-child(1) { animation-delay: 240ms; }
.streak-reveal-item:nth-child(2) { animation-delay: 380ms; }
.streak-reveal-item:nth-child(3) { animation-delay: 520ms; }
.streak-reveal-item:nth-child(4) { animation-delay: 660ms; }

@keyframes streak-reveal {
  0% {
    opacity: 0;
    transform: translateY(8px) scale(0.92);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>
