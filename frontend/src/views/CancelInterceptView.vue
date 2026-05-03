<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Capacitor } from '@capacitor/core'
import {
  SubscriptionFlowApi,
  FeedbackApi,
  type ChurnInterceptData,
  type ChurnInterceptReason,
} from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import { useEntitlementsStore } from '../stores/entitlements'
import { useSfx } from '../lib/sound'
import { useTone } from '../composables/useTone'

const router = useRouter()
const sfx = useSfx()
const ent = useEntitlementsStore()
const { t } = useTone()

type Step = 1 | 2 | 3 | 4

const step = ref<Step>(1)
const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<ChurnInterceptData | null>(null)

const selectedReason = ref<ChurnInterceptReason | null>(null)
const featureMessage = ref('')
const busy = ref(false)
const stepError = ref<string | null>(null)
const successMsg = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await SubscriptionFlowApi.churnIntercept()
    data.value = res.data.data
  } catch {
    error.value = t('cancel_load_failed')
  } finally {
    loading.value = false
  }
}

function pickReason(r: ChurnInterceptReason) {
  sfx.play('ui_tap')
  selectedReason.value = r
  stepError.value = null
}

function nextStep() {
  if (!selectedReason.value) {
    stepError.value = t('cancel_step1_pick_one')
    return
  }
  sfx.play('ui_open')
  step.value = 2
}

async function pause(months: number) {
  if (!selectedReason.value) return
  busy.value = true
  stepError.value = null
  try {
    const res = await SubscriptionFlowApi.pause(months, selectedReason.value.code)
    successMsg.value = t('cancel_pause_resumed', { date: res.data.data.resume_at })
    sfx.play('correct')
    await ent.load()
    setTimeout(() => router.push('/me'), 2000)
  } catch {
    stepError.value = t('cancel_pause_failed')
    sfx.play('wrong')
  } finally {
    busy.value = false
  }
}

async function submitFeatureFeedback() {
  if (!featureMessage.value.trim() || featureMessage.value.trim().length < 10) {
    stepError.value = t('cancel_feature_min')
    return
  }
  busy.value = true
  stepError.value = null
  try {
    await FeedbackApi.submit({
      category: 'feature',
      message: `[退訂回饋] ${featureMessage.value.trim()}`,
      app_version: (import.meta.env.VITE_APP_VERSION as string | undefined) ?? 'dev',
      device_info: Capacitor.getPlatform(),
    })
    sfx.play('correct')
    step.value = 3
  } catch {
    stepError.value = t('cancel_feature_send_failed')
  } finally {
    busy.value = false
  }
}

function goWinBack() {
  sfx.play('ui_open')
  step.value = 3
}

function reallyCancel() {
  sfx.play('ui_open')
  step.value = 4
}

async function confirmCancel() {
  if (!selectedReason.value) return
  busy.value = true
  stepError.value = null
  try {
    await SubscriptionFlowApi.cancelFeedback(selectedReason.value.code, featureMessage.value || undefined)
    // 記錄完意圖後，導去平台訂閱管理頁讓用戶實際取消
    const platform = Capacitor.getPlatform()
    let url: string
    if (platform === 'ios') url = 'itms-apps://apps.apple.com/account/subscriptions'
    else if (platform === 'android') url = 'market://details?id=com.jerosse.pandora.calendar'
    else url = 'https://js-store.com.tw/account/subscription'
    window.location.href = url
  } catch {
    stepError.value = t('cancel_op_failed')
    busy.value = false
  }
}

onMounted(load)

// 對應 reason -> Step 2 顯示哪種挽留
const offerKind = computed(() => selectedReason.value?.offer_kind ?? 'none')
</script>

<template>
  <div class="px-5 pt-10 pb-10 max-w-md mx-auto space-y-5">
    <header class="space-y-1">
      <button class="text-xs text-stone-500 font-zen mb-2" @click="router.back()">{{ t('common_back') }}</button>
      <p class="font-zen text-[11px] text-stone-400">{{ t('cancel_step_indicator', { step }) }}</p>
    </header>

    <Spinner v-if="loading" :label="t('cancel_loading')" />
    <div v-else-if="error">
      <Card tone="plain" class="text-center space-y-3">
        <p class="font-zen text-sm text-stone-600">{{ error }}</p>
        <Button @click="load">{{ t('common_retry_short') }}</Button>
      </Card>
    </div>

    <template v-else-if="data">
      <!-- Step 1: 選原因 -->
      <template v-if="step === 1">
        <Card tone="cream" class="text-center space-y-3">
          <div class="text-5xl">🥺</div>
          <h1 class="font-display text-2xl font-bold text-peach-500">{{ t('cancel_step1_title') }}</h1>
          <p class="font-zen text-sm text-stone-600 leading-relaxed">
            {{ t('cancel_step1_blurb') }}
          </p>
        </Card>

        <Card tone="plain" class="space-y-2">
          <button
            v-for="r in data.reasons"
            :key="r.code"
            class="w-full text-left px-4 py-3 rounded-2xl border transition-all"
            :class="
              selectedReason?.code === r.code
                ? 'border-peach-400 bg-peach-50 shadow-soft'
                : 'border-cream-200 bg-white hover:bg-peach-50/50'
            "
            :aria-pressed="selectedReason?.code === r.code"
            @click="pickReason(r)"
          >
            <p class="font-zen text-sm text-stone-700">{{ r.label }}</p>
          </button>
          <p v-if="stepError" class="font-zen text-xs text-sakura-500 pt-1">{{ stepError }}</p>
        </Card>

        <Button full size="lg" :disabled="!selectedReason" @click="nextStep">{{ t('cancel_step1_next') }}</Button>
      </template>

      <!-- Step 2: 依 reason 顯示挽留 offer -->
      <template v-else-if="step === 2">
        <p v-if="successMsg" class="text-center font-zen text-sm text-peach-500">{{ successMsg }}</p>

        <!-- too_expensive / 暫停 + 折扣 -->
        <template v-if="offerKind === 'pause' || offerKind === 'discount'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">{{ t('cancel_pause_title') }}</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              {{ t('cancel_pause_blurb') }}
            </p>
            <div class="grid grid-cols-3 gap-2 pt-2">
              <button
                v-for="opt in data.pause_options"
                :key="opt.months"
                class="px-3 py-3 rounded-2xl bg-white border border-peach-200 hover:bg-peach-50 transition-all active:scale-95 disabled:opacity-50"
                :disabled="busy"
                @click="pause(opt.months)"
              >
                <p class="font-display font-bold text-peach-500 text-lg leading-none">
                  {{ opt.months }}
                </p>
                <p class="font-zen text-[10px] text-stone-500 mt-1">{{ opt.label }}</p>
              </button>
            </div>
          </Card>

          <Card v-if="data.discount" tone="peach" class="space-y-2 text-center">
            <p class="font-display text-xl font-bold text-white">
              {{ t('cancel_discount_or', { percent: data.discount.percent }) }}
            </p>
            <p class="font-zen text-sm text-white/90 leading-relaxed">{{ data.discount.copy }}</p>
            <p class="font-zen text-[11px] text-white/70">{{ t('cancel_discount_valid', { days: data.discount.valid_days }) }}</p>
          </Card>
        </template>

        <!-- missing_feature / feedback -->
        <template v-else-if="offerKind === 'feedback' || offerKind === 'feature_promise'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">{{ t('cancel_feature_title') }}</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              {{ t('cancel_feature_blurb') }}
            </p>
            <label for="cancel-feature-message" class="sr-only">{{ t('cancel_feature_aria') }}</label>
            <textarea
              id="cancel-feature-message"
              v-model="featureMessage"
              rows="5"
              maxlength="2000"
              :placeholder="t('cancel_feature_placeholder')"
              :aria-label="t('cancel_feature_aria')"
              class="w-full px-4 py-3 rounded-2xl border border-cream-200 bg-white focus:outline-none focus:border-peach-300 font-zen text-sm leading-relaxed resize-none"
            />
            <Button full :loading="busy" :disabled="busy" @click="submitFeatureFeedback">
              {{ t('cancel_feature_send') }}
            </Button>
          </Card>
        </template>

        <!-- privacy_concern -->
        <template v-else-if="offerKind === 'privacy'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">{{ t('cancel_privacy_title') }}</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              {{ t('cancel_privacy_blurb') }}
            </p>
            <ul class="font-zen text-sm text-stone-600 space-y-1.5 pl-1">
              <li>{{ t('cancel_privacy_bullet_e2e') }}</li>
              <li>{{ t('cancel_privacy_bullet_export') }}</li>
              <li>{{ t('cancel_privacy_bullet_delete') }}</li>
            </ul>
            <div class="flex flex-col gap-2 pt-1">
              <Button variant="secondary" full @click="router.push('/me')">{{ t('cancel_privacy_export_btn') }}</Button>
              <Button variant="secondary" full @click="router.push('/privacy')">{{ t('cancel_privacy_policy_btn') }}</Button>
            </div>
          </Card>
        </template>

        <Card v-else tone="cream" class="space-y-3 text-center">
          <p class="font-display text-lg font-bold text-peach-500">{{ t('cancel_other_title') }}</p>
          <p class="font-zen text-sm text-stone-600">
            {{ t('cancel_other_blurb') }}
          </p>
        </Card>

        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>

        <Button variant="ghost" full @click="goWinBack">{{ t('cancel_still_cancel') }}</Button>
      </template>

      <!-- Step 3: win_back -->
      <template v-else-if="step === 3">
        <Card tone="cream" class="text-center space-y-3">
          <div class="text-5xl">💛</div>
          <h2 class="font-display text-xl font-bold text-peach-500">{{ data.win_back.headline }}</h2>
          <p class="font-zen text-sm text-stone-600 leading-relaxed whitespace-pre-line">
            {{ data.win_back.body }}
          </p>
        </Card>

        <Card tone="plain" class="space-y-3">
          <p class="font-display font-bold text-peach-500 text-sm text-center">
            {{ t('cancel_step3_pause_title', { months: data.win_back.pause_default_months }) }}
          </p>
          <p class="font-zen text-xs text-stone-500 text-center leading-relaxed">
            {{ t('cancel_step3_pause_blurb') }}
          </p>
          <Button
            full
            size="lg"
            :loading="busy"
            :disabled="busy"
            @click="pause(data.win_back.pause_default_months)"
          >
            {{ t('cancel_step3_pause_btn', { months: data.win_back.pause_default_months }) }}
          </Button>
          <Button variant="ghost" full @click="reallyCancel">{{ t('cancel_step3_really_cancel') }}</Button>
        </Card>

        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>
      </template>

      <!-- Step 4: confirm + 平台導向 -->
      <template v-else-if="step === 4">
        <Card tone="plain" class="space-y-3">
          <h2 class="font-display text-xl font-bold text-peach-500 text-center">
            {{ t('cancel_step4_title') }}
          </h2>
          <p class="font-zen text-sm text-stone-600 leading-relaxed">
            {{ t('cancel_step4_platform_blurb', { platform: Capacitor.getPlatform() === 'ios' ? 'Apple' : Capacitor.getPlatform() === 'android' ? 'Google' : '平台' }) }}
          </p>
          <p class="font-zen text-xs text-stone-500 leading-relaxed">
            {{ t('cancel_step4_records_safe') }}
          </p>
          <Button
            full
            size="lg"
            variant="danger"
            :loading="busy"
            :disabled="busy"
            @click="confirmCancel"
          >
            {{ t('cancel_step4_go_settings') }}
          </Button>
          <Button variant="ghost" full @click="step = 1">{{ t('cancel_step4_reconsider') }}</Button>
        </Card>
        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>
      </template>
    </template>
  </div>
</template>
