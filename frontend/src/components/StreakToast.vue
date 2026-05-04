<script setup lang="ts">
import { computed } from 'vue'
import { useStreakToast } from '../composables/useStreakToast'

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — daily login streak toast.
 *
 * Mount 一次（App.vue），由 useStreakToast() composable 控制顯示。
 *
 * 行為：
 *   - is_first_today=true 普通日：顯示 3s「連續第 N 天 🔥」
 *   - is_milestone=true 里程碑日：5s + 朵朵頭像 + 導師語氣文案
 *
 * 文案紅線（集團硬規則）：
 *   - 用「妳 / 你 / 朋友 / 夥伴」，不寫「您 / 會員 / 用戶」
 *   - 不寫「集團」「公司」
 *   - 朵朵 = 導師角色（給建議與提醒），文案口吻溫柔但不做療效宣稱
 */

const { state, visible, dismiss } = useStreakToast()

const streak = computed(() => state.value?.current_streak ?? 0)
const isMilestone = computed(() => !!state.value?.is_milestone)

const milestoneMessage = computed(() => {
  if (!state.value?.is_milestone) return ''
  const n = state.value.current_streak
  return `妳已經連續 ${n} 天了，朋友！`
})
</script>

<template>
  <Transition name="streak-fade">
    <div
      v-if="visible && state && state.is_first_today"
      class="streak-toast fixed top-4 left-1/2 -translate-x-1/2 z-[70] pointer-events-auto"
      :class="{ 'streak-milestone': isMilestone }"
      style="padding-top: env(safe-area-inset-top)"
      role="status"
      aria-live="polite"
      data-test="streak-toast"
      @click="dismiss"
    >
      <div
        class="flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg ring-2 ring-white/40"
        :class="
          isMilestone
            ? 'bg-gradient-to-br from-peach-500 to-sakura-500 text-white min-w-[260px]'
            : 'bg-gradient-to-br from-cream-100 to-peach-100 text-stone-700 min-w-[200px]'
        "
      >
        <!-- Milestone：朵朵頭像（inline SVG，療癒手繪 anchor 風） -->
        <span
          v-if="isMilestone"
          aria-hidden="true"
          class="inline-flex shrink-0 w-10 h-10 rounded-full bg-white/30 ring-2 ring-white/60 items-center justify-center"
        >
          <svg viewBox="0 0 64 64" width="32" height="32" fill="none">
            <!-- 朵朵 dodo NPC chibi anchor — 療癒可愛 vector flat（對齊集團 SVG style guide v1） -->
            <ellipse cx="32" cy="40" rx="20" ry="18" fill="#FCE6D6" />
            <ellipse cx="32" cy="26" rx="16" ry="15" fill="#FCE6D6" />
            <!-- bonnet / hood -->
            <path
              d="M16 22 Q32 6 48 22 Q44 16 32 14 Q20 16 16 22 Z"
              fill="#FFB8A8"
            />
            <!-- eyes -->
            <ellipse cx="26" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
            <ellipse cx="38" cy="28" rx="1.6" ry="2.2" fill="#5C3A2E" />
            <!-- cheeks -->
            <circle cx="22" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
            <circle cx="42" cy="32" r="2" fill="#FFAFA0" opacity="0.7" />
            <!-- mouth (smile) -->
            <path d="M28 33 Q32 36 36 33" stroke="#5C3A2E" stroke-width="1.4" stroke-linecap="round" fill="none" />
          </svg>
        </span>

        <!-- 普通日：火焰 emoji（簡單夠用） -->
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
          <p v-else class="text-xs opacity-80">
            朵朵看見妳今天又來了
          </p>
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

/* Milestone 額外 sparkle bounce */
.streak-milestone {
  animation: streak-bounce 600ms cubic-bezier(0.34, 1.56, 0.64, 1) 1;
}

@keyframes streak-bounce {
  0% {
    transform: translateX(-50%) scale(0.8);
  }
  60% {
    transform: translateX(-50%) scale(1.06);
  }
  100% {
    transform: translateX(-50%) scale(1);
  }
}
</style>
