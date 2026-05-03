<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import Character from '../components/Character.vue'
import OnboardingStep1Period from './onboarding/OnboardingStep1Period.vue'
import OnboardingStep2CycleLength from './onboarding/OnboardingStep2CycleLength.vue'
import OnboardingStep3Goal, { type OnboardingGoal } from './onboarding/OnboardingStep3Goal.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()

const router = useRouter()

const step = ref<1 | 2 | 3>(1)
const lastPeriodAt = ref<string | null>(null)
const unsure = ref(false)
const cycleLength = ref<number>(28)
const goal = ref<OnboardingGoal | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const ONBOARDING_DONE_KEY = 'pandora_calendar_onboarding_done'

function goNextFromStep1() {
  step.value = 2
}

function goNextFromStep2() {
  step.value = 3
}

function goBack() {
  if (step.value === 3) step.value = 2
  else if (step.value === 2) step.value = 1
}

async function submit() {
  if (!goal.value) return
  loading.value = true
  error.value = null
  try {
    /**
     * TODO(backend): 提供 POST /api/v1/onboarding/complete
     *
     * Request body:
     *   {
     *     last_period_at: string | null  // YYYY-MM-DD（unsure 時為 null）
     *     cycle_length: number           // 21-45
     *     goal: 'health' | 'conceive' | 'avoid' | 'unsure'
     *   }
     *
     * 行為：
     *   - 若 last_period_at 不為 null，建立一筆 Cycle 記錄（peak_flow null）
     *   - 把 cycle_length 寫到 user.preferences.cycle_length（覆寫 CyclePredictor 預設）
     *   - 把 goal 寫到 user.preferences.calendar_goal
     *   - 標記 user.preferences.onboarding_completed_at = now()
     *
     * Response: { data: { onboarded: true } }
     *
     * 在 endpoint 上線前，前端會 swallow 失敗，仍導向 calendar
     * （local flag 確保下次不會再被導回 onboarding）
     */
    await api.post('/v1/onboarding/complete', {
      last_period_at: unsure.value ? null : lastPeriodAt.value,
      cycle_length: cycleLength.value,
      goal: goal.value,
    })
  } catch (e: any) {
    // backend endpoint 還沒上線時不要擋住 onboarding 體驗
    if (e?.response?.status && e.response.status !== 404) {
      error.value = e?.response?.data?.message ?? t('onboarding_error_sync')
    }
  } finally {
    try {
      localStorage.setItem(ONBOARDING_DONE_KEY, '1')
    } catch {
      /* localStorage 可能在 private mode 被擋 */
    }
    loading.value = false
    router.replace('/calendar')
  }
}
</script>

<template>
  <div class="min-h-screen bg-dawn-gradient px-5 pt-10 pb-8">
    <div class="max-w-md mx-auto space-y-6">
      <header class="text-center space-y-2">
        <div class="flex justify-center">
          <Character species="dodo" mood="happy" :size="100" :show-halo="true" :floaty="true" />
        </div>
        <h1 class="font-display text-2xl font-bold text-peach-500">{{ t('onboarding_title') }}</h1>
        <div class="flex justify-center gap-1.5 pt-1">
          <span
            v-for="n in 3"
            :key="n"
            class="h-1.5 rounded-full transition-all"
            :class="n === step ? 'w-8 bg-peach-500' : n < step ? 'w-4 bg-peach-300' : 'w-4 bg-cream-200'"
          />
        </div>
      </header>

      <div class="bg-white rounded-3xl p-5 shadow-soft">
        <OnboardingStep1Period
          v-if="step === 1"
          v-model="lastPeriodAt"
          v-model:unsure="unsure"
          @next="goNextFromStep1"
        />
        <OnboardingStep2CycleLength
          v-else-if="step === 2"
          v-model="cycleLength"
          @next="goNextFromStep2"
          @back="goBack"
        />
        <OnboardingStep3Goal
          v-else-if="step === 3"
          v-model="goal"
          :loading="loading"
          @submit="submit"
          @back="goBack"
        />
        <p v-if="error" class="mt-3 text-xs text-sakura-500 text-center font-zen">{{ error }}</p>
      </div>

      <!-- 隱私差異化 -->
      <div class="text-center space-y-1 pt-2" data-test="onboarding-privacy">
        <p class="font-display font-bold text-peach-500 text-base">{{ t('privacy_yours') }}</p>
        <p class="font-zen text-[12px] text-stone-500 leading-relaxed">
          {{ t('privacy_blurb_long') }}
        </p>
      </div>
    </div>
  </div>
</template>
