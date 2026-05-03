<script setup lang="ts">
import { onMounted, reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useHealthKit, type HealthDataType, type SyncResult } from '../composables/useHealthKit'
import { ReflectionApi, type HealthReflection } from '../api'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const reflection = ref<HealthReflection | null>(null)
const reflectionLoading = ref(true)

async function loadReflection() {
  reflectionLoading.value = true
  try {
    const r = await ReflectionApi.today()
    reflection.value = r.data.data
  } catch {
    reflection.value = null
  } finally {
    reflectionLoading.value = false
  }
}

function gotoActionToday() {
  router.push('/me/action-today')
}

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
  if (!platform.available) return t('health_platform_unsupported')
  return platform.platform === 'ios' ? t('health_platform_ios') : t('health_platform_android')
})

const lastSyncedLabel = computed(() => {
  if (!lastSyncedAt.value) return t('health_last_sync_never')
  try {
    const d = new Date(lastSyncedAt.value)
    return t('health_last_sync_prefix', { at: d.toLocaleString('zh-TW', { hour12: false }) })
  } catch {
    return t('health_last_sync_unknown')
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
    lastError.value = t('health_web_only')
    return
  }
  const types: HealthDataType[] = [...enabledKinds.value]
  if (toggles.menstrual_flow) types.push('menstrual_flow')
  if (types.length === 0) {
    lastError.value = t('health_must_pick_one')
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

const KIND_META = computed<Record<HealthDataType, { label: string; emoji: string; desc: string }>>(() => ({
  bbt: { label: t('health_kind_bbt_label'), emoji: '🌡', desc: t('health_kind_bbt_desc') },
  steps: { label: t('health_kind_steps_label'), emoji: '👟', desc: t('health_kind_steps_desc') },
  sleep: { label: t('health_kind_sleep_label'), emoji: '🌙', desc: t('health_kind_sleep_desc') },
  menstrual_flow: { label: t('health_kind_menstrual_label'), emoji: '🩸', desc: t('health_kind_menstrual_desc') },
}))

onMounted(() => {
  loadToggles()
  refreshAvailability()
  loadReflection()
})

const reflectionSeverityClass = computed(() => {
  const s = reflection.value?.severity
  if (s === 'heads_up') return 'bg-[#fce8e0] border-[#f5b89e]'
  if (s === 'notice') return 'bg-[#fdf3dc] border-[#ecc97a]'
  return 'bg-[#e7f0fa] border-[#b8d0e8]'
})
</script>

<template>
  <div class="min-h-screen bg-[#fbf6ee] pb-32">
    <header class="sticky top-0 z-10 bg-[#fbf6ee]/95 backdrop-blur border-b border-[#e8dcc6] px-4 py-3 flex items-center gap-3">
      <button class="text-[#7a6649] text-sm" @click="$router.back()">{{ t('health_back') }}</button>
      <h1 class="text-base font-semibold text-[#3d2f1f]">{{ t('health_title') }}</h1>
    </header>

    <main class="px-4 py-5 space-y-4 max-w-md mx-auto">
      <!-- 朵朵當天反饋（reflection）— Premium-only，後端 402 → reflection.value 為 null 自動隱藏 -->
      <section
        v-if="reflectionLoading"
        class="rounded-2xl px-4 py-3 bg-[#f5ecdc] text-[#7a6649] text-sm text-center"
        data-test="reflection-loading"
      >
        {{ t('reflection_loading') }}
      </section>
      <section
        v-else-if="reflection"
        class="rounded-2xl border px-4 py-3"
        :class="reflectionSeverityClass"
        data-test="reflection-card"
      >
        <div class="flex items-start gap-2">
          <span class="text-2xl shrink-0" aria-hidden="true">🌸</span>
          <div class="flex-1 min-w-0">
            <p class="text-[11px] tracking-wide text-stone-500 mb-1">{{ t('dodo_say') }}</p>
            <p class="text-[15px] leading-relaxed text-stone-800 font-medium">{{ reflection.message }}</p>
            <button
              type="button"
              class="mt-2 text-[12px] underline text-[#7a6649]"
              data-test="reflection-cta"
              @click="gotoActionToday"
            >
              {{ t('reflection_cta_action') }}
            </button>
          </div>
        </div>
      </section>
      <section
        v-else
        class="rounded-2xl px-4 py-3 bg-white border border-[#ece2cf] text-[#9b8763] text-xs text-center"
        data-test="reflection-empty"
      >
        {{ t('reflection_empty') }}
      </section>

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
              <template v-if="checking">{{ t('health_detecting') }}</template>
              <template v-else-if="available">{{ t('health_supported', { platform: platformLabel }) }}</template>
              <template v-else-if="platform.platform === 'web'">{{ t('health_web_only') }}</template>
              <template v-else>{{ t('health_unsupported_manual') }}</template>
            </p>
            <p v-if="!available && !checking" class="text-xs mt-1 opacity-80">
              {{ platform.reason ?? t('health_unsupported_default_reason') }}
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
              :aria-label="t('health_toggle_aria', { label: KIND_META[kind].label })"
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
          <span v-if="syncing">{{ t('health_syncing') }}</span>
          <span v-else>{{ t('health_sync_now') }}</span>
        </button>

        <button
          v-if="toggles.menstrual_flow && available"
          class="mt-2 w-full rounded-2xl py-2.5 text-sm text-[#7a6649] border border-[#e0d4ba] bg-white"
          @click="handleSyncMenstrualToHealth"
        >
          {{ t('health_write_today') }}
        </button>

        <p class="text-xs text-[#9b8763] mt-2 text-center">{{ lastSyncedLabel }}</p>
      </section>

      <!-- result 顯示 -->
      <section v-if="totalImported > 0 || Object.keys(lastResults).length > 0" class="bg-white rounded-2xl px-4 py-3 border border-[#ece2cf]">
        <p class="text-sm font-medium text-[#3d2f1f] mb-2">{{ t('health_result_title') }}</p>
        <ul class="text-xs text-[#7a6649] space-y-1">
          <li v-for="(r, k) in lastResults" :key="k">
            <span class="font-medium">{{ t('health_result_kind_prefix', { label: KIND_META[k as HealthDataType].label }) }}</span>
            {{ t('health_result_imported', { n: r?.imported ?? 0 }) }}
            <span v-if="(r?.duplicates ?? 0) > 0" class="opacity-70">{{ t('health_result_duplicates', { n: r?.duplicates ?? 0 }) }}</span>
            <span v-if="(r?.errors?.length ?? 0) > 0" class="text-[#b85c2e]">{{ t('health_result_errors', { n: r?.errors?.length ?? 0 }) }}</span>
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
          {{ t('health_retry') }}
        </button>
      </section>

      <!-- 隱私說明 -->
      <section class="rounded-2xl bg-[#f5ecdc]/60 px-4 py-3 text-xs text-[#7a6649] leading-relaxed">
        <p class="font-medium text-[#3d2f1f] mb-1">{{ t('health_privacy_title') }}</p>
        <p>{{ t('health_privacy_blurb') }}</p>
      </section>
    </main>
  </div>
</template>
