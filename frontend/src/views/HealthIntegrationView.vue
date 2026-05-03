<script setup lang="ts">
import { onMounted, reactive, ref, computed } from 'vue'
import { useHealthKit, type HealthDataType, type SyncResult } from '../composables/useHealthKit'

const {
  lastSyncedAt,
  syncing,
  lastError,
  platformInfo,
  isAvailable,
  requestAuth,
  syncRecent,
  writeMenstrualFlow,
} = useHealthKit()

const platform = platformInfo()
const available = ref(false)
const checking = ref(true)
const lastResults = ref<Partial<Record<HealthDataType, SyncResult>>>({})

const toggles = reactive<Record<HealthDataType, boolean>>({
  bbt: true,
  steps: true,
  sleep: true,
  menstrual_flow: false,
})

const STORAGE_KEY = 'pandora_calendar_health_toggles'

function loadToggles() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return
    const parsed = JSON.parse(raw) as Partial<Record<HealthDataType, boolean>>
    for (const k of Object.keys(toggles) as HealthDataType[]) {
      if (typeof parsed[k] === 'boolean') toggles[k] = parsed[k]!
    }
  } catch {
    /* ignore */
  }
}

function saveToggles() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(toggles))
  } catch {
    /* private mode */
  }
}

const enabledKinds = computed<HealthDataType[]>(() =>
  (['bbt', 'steps', 'sleep'] as HealthDataType[]).filter((k) => toggles[k])
)

const platformLabel = computed(() => {
  if (!platform.available) return '此裝置不支援'
  return platform.platform === 'ios' ? 'HealthKit（iOS）' : 'Health Connect（Android）'
})

const lastSyncedLabel = computed(() => {
  if (!lastSyncedAt.value) return '尚未同步過'
  try {
    const d = new Date(lastSyncedAt.value)
    return `上次同步：${d.toLocaleString('zh-TW', { hour12: false })}`
  } catch {
    return '上次同步：—'
  }
})

const totalImported = computed(() =>
  Object.values(lastResults.value).reduce((sum, r) => sum + (r?.imported ?? 0), 0)
)

async function refreshAvailability() {
  checking.value = true
  available.value = await isAvailable()
  checking.value = false
}

async function handleAuthAndSync() {
  if (!available.value) {
    lastError.value = '此功能需要在 iOS / Android App 中使用'
    return
  }
  const types: HealthDataType[] = [...enabledKinds.value]
  if (toggles.menstrual_flow) types.push('menstrual_flow')
  if (types.length === 0) {
    lastError.value = '請至少開啟一項要同步的資料'
    return
  }
  const granted = await requestAuth(types)
  if (!granted) return
  const results = await syncRecent(7, enabledKinds.value)
  lastResults.value = results
}

async function handleSyncMenstrualToHealth() {
  if (!toggles.menstrual_flow) return
  const today = new Date().toISOString().slice(0, 10)
  const ok = await writeMenstrualFlow(today, 2)
  if (ok) {
    lastError.value = null
  }
}

const KIND_META: Record<HealthDataType, { label: string; emoji: string; desc: string }> = {
  bbt: { label: '基礎體溫 (BBT)', emoji: '🌡', desc: '每天醒來的基礎體溫，幫助朵朵更準確預測排卵期' },
  steps: { label: '步數', emoji: '👟', desc: '日常活動量，作為體力曲線參考' },
  sleep: { label: '睡眠時數', emoji: '🌙', desc: '了解睡眠跟妳週期的關聯' },
  menstrual_flow: { label: '同步經期到 Apple Health / Health Connect', emoji: '🩸', desc: '把妳在月曆記錄的經期寫回系統健康 App，跨 App 共用' },
}

onMounted(() => {
  loadToggles()
  refreshAvailability()
})
</script>

<template>
  <div class="min-h-screen bg-[#fbf6ee] pb-32">
    <header class="sticky top-0 z-10 bg-[#fbf6ee]/95 backdrop-blur border-b border-[#e8dcc6] px-4 py-3 flex items-center gap-3">
      <button class="text-[#7a6649] text-sm" @click="$router.back()">← 返回</button>
      <h1 class="text-base font-semibold text-[#3d2f1f]">健康資料同步</h1>
    </header>

    <main class="px-4 py-5 space-y-4 max-w-md mx-auto">
      <!-- 平台 banner -->
      <section
        class="rounded-2xl p-4"
        :class="
          checking
            ? 'bg-[#f5ecdc] text-[#7a6649]'
            : available
              ? 'bg-[#dff5e8] text-[#1f5d3a]'
              : 'bg-[#fbe4d4] text-[#8a4a1f]'
        "
      >
        <div class="flex items-start gap-3">
          <span class="text-2xl">{{ checking ? '⏳' : available ? '✅' : '📱' }}</span>
          <div class="flex-1">
            <p class="font-medium">
              <template v-if="checking">正在偵測裝置…</template>
              <template v-else-if="available">妳的裝置支援 {{ platformLabel }}</template>
              <template v-else-if="platform.platform === 'web'">此功能需要在 iOS / Android App 中使用</template>
              <template v-else>此裝置不支援自動同步，仍可手動記錄</template>
            </p>
            <p v-if="!available && !checking" class="text-xs mt-1 opacity-80">
              {{ platform.reason ?? '請開啟 App 並確認系統健康 App 已啟用' }}
            </p>
          </div>
        </div>
      </section>

      <!-- toggles -->
      <section class="bg-white rounded-2xl shadow-sm border border-[#ece2cf] divide-y divide-[#f1e7d4]">
        <div
          v-for="kind in (['bbt', 'steps', 'sleep', 'menstrual_flow'] as HealthDataType[])"
          :key="kind"
          class="px-4 py-3 flex items-start gap-3"
        >
          <span class="text-2xl pt-1">{{ KIND_META[kind].emoji }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-[#3d2f1f]">{{ KIND_META[kind].label }}</p>
            <p class="text-xs text-[#9b8763] mt-0.5">{{ KIND_META[kind].desc }}</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer pt-1.5">
            <span class="sr-only">{{ KIND_META[kind].label }}</span>
            <input
              type="checkbox"
              class="sr-only peer"
              :checked="toggles[kind]"
              :disabled="!available"
              :aria-label="`啟用 ${KIND_META[kind].label} 同步`"
              @change="(toggles[kind] = ($event.target as HTMLInputElement).checked), saveToggles()"
            />
            <div
              class="w-11 h-6 bg-[#e0d4ba] rounded-full peer-checked:bg-[#c89b6a] peer-disabled:opacity-50 transition relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"
            ></div>
          </label>
        </div>
      </section>

      <!-- sync 按鈕 -->
      <section>
        <button
          class="w-full rounded-2xl py-3 font-medium text-white shadow-sm transition"
          :class="
            available && !syncing
              ? 'bg-[#c89b6a] hover:bg-[#b48653] active:scale-[0.98]'
              : 'bg-[#d8c8a8] cursor-not-allowed'
          "
          :disabled="!available || syncing"
          @click="handleAuthAndSync"
        >
          <span v-if="syncing">同步中…</span>
          <span v-else>立刻同步最近 7 天</span>
        </button>

        <button
          v-if="toggles.menstrual_flow && available"
          class="mt-2 w-full rounded-2xl py-2.5 text-sm text-[#7a6649] border border-[#e0d4ba] bg-white"
          @click="handleSyncMenstrualToHealth"
        >
          將今天記錄為經期寫回系統健康
        </button>

        <p class="text-xs text-[#9b8763] mt-2 text-center">{{ lastSyncedLabel }}</p>
      </section>

      <!-- result 顯示 -->
      <section v-if="totalImported > 0 || Object.keys(lastResults).length > 0" class="bg-white rounded-2xl px-4 py-3 border border-[#ece2cf]">
        <p class="text-sm font-medium text-[#3d2f1f] mb-2">本次同步結果</p>
        <ul class="text-xs text-[#7a6649] space-y-1">
          <li v-for="(r, k) in lastResults" :key="k">
            <span class="font-medium">{{ KIND_META[k as HealthDataType].label }}：</span>
            匯入 {{ r?.imported ?? 0 }} 筆
            <span v-if="(r?.duplicates ?? 0) > 0" class="opacity-70">（重複 {{ r?.duplicates }}）</span>
            <span v-if="(r?.errors?.length ?? 0) > 0" class="text-[#b85c2e]">·{{ r?.errors?.length }} 個錯誤</span>
          </li>
        </ul>
      </section>

      <!-- 錯誤訊息 -->
      <section v-if="lastError" class="rounded-2xl bg-[#fce8e0] text-[#8a3a1c] px-4 py-3 text-sm">
        <p class="flex items-start gap-2">
          <span>⚠️</span>
          <span>{{ lastError }}</span>
        </p>
        <button
          v-if="available"
          class="mt-2 text-xs underline opacity-80"
          @click="(lastError = null), handleAuthAndSync()"
        >
          再試一次
        </button>
      </section>

      <!-- 隱私說明 -->
      <section class="rounded-2xl bg-[#f5ecdc]/60 px-4 py-3 text-xs text-[#7a6649] leading-relaxed">
        <p class="font-medium text-[#3d2f1f] mb-1">🔒 隱私說明</p>
        <p>資料只在妳的裝置上處理，雲端只儲存週期相關統計（不包含原始 GPS / 心率細節）。妳隨時可以在系統設定關閉授權，月曆會自動回到手動記錄模式。</p>
      </section>
    </main>
  </div>
</template>
