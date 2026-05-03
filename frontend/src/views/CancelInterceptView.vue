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

const router = useRouter()
const sfx = useSfx()
const ent = useEntitlementsStore()

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
    error.value = '載入失敗，請稍後再試'
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
    stepError.value = '先告訴朵朵一個原因'
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
    successMsg.value = `已暫停到 ${res.data.data.resume_at}，朵朵會再陪妳回來`
    sfx.play('correct')
    await ent.load()
    setTimeout(() => router.push('/me'), 2000)
  } catch {
    stepError.value = '暫停失敗，請稍後再試'
    sfx.play('wrong')
  } finally {
    busy.value = false
  }
}

async function submitFeatureFeedback() {
  if (!featureMessage.value.trim() || featureMessage.value.trim().length < 10) {
    stepError.value = '至少寫 10 個字，朵朵才能聽懂'
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
    stepError.value = '送出失敗，請稍後再試'
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
    stepError.value = '操作失敗，請稍後再試'
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
      <button class="text-xs text-stone-500 font-zen mb-2" @click="router.back()">← 返回</button>
      <p class="font-zen text-[11px] text-stone-400">第 {{ step }} / 4 步</p>
    </header>

    <Spinner v-if="loading" label="朵朵翻翻紀錄中…" />
    <div v-else-if="error">
      <Card tone="plain" class="text-center space-y-3">
        <p class="font-zen text-sm text-stone-600">{{ error }}</p>
        <Button @click="load">再試一次</Button>
      </Card>
    </div>

    <template v-else-if="data">
      <!-- Step 1: 選原因 -->
      <template v-if="step === 1">
        <Card tone="cream" class="text-center space-y-3">
          <div class="text-5xl">🥺</div>
          <h1 class="font-display text-2xl font-bold text-peach-500">妳要離開了嗎？</h1>
          <p class="font-zen text-sm text-stone-600 leading-relaxed">
            朵朵想知道原因，讓我有機會做得更好
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

        <Button full size="lg" :disabled="!selectedReason" @click="nextStep">下一步</Button>
      </template>

      <!-- Step 2: 依 reason 顯示挽留 offer -->
      <template v-else-if="step === 2">
        <p v-if="successMsg" class="text-center font-zen text-sm text-peach-500">{{ successMsg }}</p>

        <!-- too_expensive / 暫停 + 折扣 -->
        <template v-if="offerKind === 'pause' || offerKind === 'discount'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">先暫停一下，怎麼樣？</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              暫停期間妳不會被扣款，紀錄都保留。隨時想回來都可以。
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
              或者，{{ data.discount.percent }}% 折扣
            </p>
            <p class="font-zen text-sm text-white/90 leading-relaxed">{{ data.discount.copy }}</p>
            <p class="font-zen text-[11px] text-white/70">{{ data.discount.valid_days }} 天內有效</p>
          </Card>
        </template>

        <!-- missing_feature / feedback -->
        <template v-else-if="offerKind === 'feedback' || offerKind === 'feature_promise'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">告訴朵朵妳想要什麼</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              妳缺的功能，可能朵朵正在做。直接告訴我，下次更新就有可能看到。
            </p>
            <textarea
              v-model="featureMessage"
              rows="5"
              maxlength="2000"
              placeholder="妳希望朵朵能做到的…"
              class="w-full px-4 py-3 rounded-2xl border border-cream-200 bg-white focus:outline-none focus:border-peach-300 font-zen text-sm leading-relaxed resize-none"
            />
            <Button full :loading="busy" :disabled="busy" @click="submitFeatureFeedback">
              送給朵朵
            </Button>
          </Card>
        </template>

        <!-- privacy_concern -->
        <template v-else-if="offerKind === 'privacy'">
          <Card tone="cream" class="space-y-3">
            <h2 class="font-display text-xl font-bold text-peach-500">妳的資料只屬於妳</h2>
            <p class="font-zen text-sm text-stone-600 leading-relaxed">
              我們不賣資料、不放廣告、不分享給第三方。妳隨時可以匯出或刪除。
            </p>
            <ul class="font-zen text-sm text-stone-600 space-y-1.5 pl-1">
              <li>🔒 端到端加密儲存</li>
              <li>📥 妳可以匯出 PDF / CSV 完整紀錄</li>
              <li>🗑 妳可以一鍵刪除全部資料</li>
            </ul>
            <div class="flex flex-col gap-2 pt-1">
              <Button variant="secondary" full @click="router.push('/me')">前往匯出資料</Button>
              <Button variant="secondary" full @click="router.push('/privacy')">看隱私政策</Button>
            </div>
          </Card>
        </template>

        <Card v-else tone="cream" class="space-y-3 text-center">
          <p class="font-display text-lg font-bold text-peach-500">朵朵會繼續努力</p>
          <p class="font-zen text-sm text-stone-600">
            謝謝妳曾經給朵朵機會陪妳。
          </p>
        </Card>

        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>

        <Button variant="ghost" full @click="goWinBack">我還是要取消</Button>
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
            最後一個選項：先暫停 {{ data.win_back.pause_default_months }} 個月
          </p>
          <p class="font-zen text-xs text-stone-500 text-center leading-relaxed">
            不用扣款，紀錄都保留。妳之後想回來，朵朵就在這。
          </p>
          <Button
            full
            size="lg"
            :loading="busy"
            :disabled="busy"
            @click="pause(data.win_back.pause_default_months)"
          >
            好，先暫停 {{ data.win_back.pause_default_months }} 個月
          </Button>
          <Button variant="ghost" full @click="reallyCancel">不了，我要取消訂閱</Button>
        </Card>

        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>
      </template>

      <!-- Step 4: confirm + 平台導向 -->
      <template v-else-if="step === 4">
        <Card tone="plain" class="space-y-3">
          <h2 class="font-display text-xl font-bold text-peach-500 text-center">
            最後一步
          </h2>
          <p class="font-zen text-sm text-stone-600 leading-relaxed">
            訂閱由 {{ Capacitor.getPlatform() === 'ios' ? 'Apple' : Capacitor.getPlatform() === 'android' ? 'Google' : '平台' }}
            管理，朵朵這邊只能記錄妳的回饋。下一步會把妳帶到訂閱設定，妳在那邊按取消即可。
          </p>
          <p class="font-zen text-xs text-stone-500 leading-relaxed">
            妳的紀錄不會被刪除。隨時可以再開啟訂閱。
          </p>
          <Button
            full
            size="lg"
            variant="danger"
            :loading="busy"
            :disabled="busy"
            @click="confirmCancel"
          >
            前往訂閱設定取消
          </Button>
          <Button variant="ghost" full @click="step = 1">再想想</Button>
        </Card>
        <p v-if="stepError" class="text-center font-zen text-xs text-sakura-500">{{ stepError }}</p>
      </template>
    </template>
  </div>
</template>
