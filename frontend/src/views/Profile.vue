<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getStoredUser, logout, deleteCalendarData } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import { pushSupport, enablePush, disablePush } from '../lib/push'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Character from '../components/Character.vue'
import { useSfx } from '../lib/sound'
import { getPet, savePet } from '../lib/character'
import { getCurrentLevel, getCurrentXp } from '../lib/gamification'
import { JourneyApi, type JourneyData, ExportApi, PaywallRequiredError } from '../api'
import {
  isLockEnabled,
  setLockEnabled,
  isBiometricAvailable,
  verify,
  type BiometricAvailability,
} from '../composables/useAppLock'
import { Capacitor } from '@capacitor/core'
import { useInclusiveMode } from '../composables/useInclusiveMode'
import { useTone } from '../composables/useTone'

const router = useRouter()
const user = getStoredUser()
const ent = useEntitlementsStore()
const sfx = useSfx()
const inclusiveMode = useInclusiveMode()
const { t } = useTone()

function toggleInclusive() {
  inclusiveMode.value = !inclusiveMode.value
  sfx.play('ui_tap')
}
const pet = ref(getPet())
const level = ref(getCurrentLevel())
const xp = ref(getCurrentXp())
const muted = ref(sfx.isMuted())
const pushState = ref(pushSupport())
const pushBusy = ref(false)
const pushMessage = ref<string | null>(null)
const pushEnabled = ref(pushState.value.permission === 'granted')

async function togglePush() {
  pushBusy.value = true
  pushMessage.value = null
  if (pushEnabled.value) {
    await disablePush()
    pushEnabled.value = false
    pushMessage.value = '已關閉通知'
  } else {
    const r = await enablePush()
    if (r.ok) {
      pushEnabled.value = true
      pushMessage.value = '✓ 通知已開啟，朵朵會在重要時間點提醒妳'
    } else {
      pushMessage.value = '無法開啟：' + (r.error ?? '請檢查瀏覽器通知權限')
    }
  }
  pushBusy.value = false
}

const journey = ref<JourneyData | null>(null)
const journeyLoading = ref(true)

const nextMilestone = computed(() => {
  if (!journey.value) return null
  return (
    journey.value.milestones.find(
      (m) => !m.unlocked && m.target && (m.progress ?? 0) < m.target,
    ) ?? null
  )
})

const levelProgressPct = computed(() => {
  if (!journey.value) return 0
  const need = journey.value.need_for_next_level
  if (!need || need <= 0) return 100
  return Math.min(100, Math.round((journey.value.progress_in_level / need) * 100))
})

// === 生物辨識鎖（安全與隱私） ===
const isNative = Capacitor.isNativePlatform()
const lockEnabled = ref(isLockEnabled())
const lockBusy = ref(false)
const lockMessage = ref<string | null>(null)
const biometricInfo = ref<BiometricAvailability>({ available: false, reason: 'web' })

const lockUnsupportedHint = computed(() => {
  if (!isNative) return null // Web：整個 toggle 不顯示
  if (biometricInfo.value.available) return null
  switch (biometricInfo.value.reason) {
    case 'not_enrolled':
      return '妳的裝置還沒設定 Face ID / 指紋，請先到系統設定建立'
    case 'no_hardware':
      return '妳的裝置不支援生物辨識'
    default:
      return '目前無法使用生物辨識，請稍後再試'
  }
})

async function toggleLock() {
  if (lockBusy.value) return
  lockBusy.value = true
  lockMessage.value = null
  const turningOn = !lockEnabled.value
  try {
    if (turningOn) {
      // 先驗證一次確認可用，失敗回滾不存
      const ok = await verify('啟用 App 鎖定')
      if (!ok) {
        lockMessage.value = '驗證未通過，沒有啟用鎖定'
        lockEnabled.value = false
        return
      }
      setLockEnabled(true)
      lockEnabled.value = true
      lockMessage.value = '✓ 已啟用 App 鎖定'
      sfx.play('correct')
    } else {
      // 關閉前也驗證一次（避免別人拿到手機就關掉）
      const ok = await verify('關閉 App 鎖定')
      if (!ok) {
        lockMessage.value = '驗證未通過，鎖定維持開啟'
        lockEnabled.value = true
        return
      }
      setLockEnabled(false)
      lockEnabled.value = false
      lockMessage.value = '已關閉 App 鎖定'
    }
  } finally {
    lockBusy.value = false
  }
}

onMounted(async () => {
  ent.load()
  if (isNative) {
    biometricInfo.value = await isBiometricAvailable()
    // 如果硬體變得不可用（例如重置 Face ID），自動關掉避免卡死
    if (!biometricInfo.value.available && lockEnabled.value) {
      setLockEnabled(false)
      lockEnabled.value = false
    }
  }
  try {
    const res = await JourneyApi.show()
    journey.value = res.data.data
  } catch {
    journey.value = null
  } finally {
    journeyLoading.value = false
  }
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 6) return '晚安'
  if (h < 12) return '早安'
  if (h < 18) return '午安'
  return '晚安'
})

function toggleMute() {
  muted.value = sfx.toggle()
  if (!muted.value) sfx.play('ui_tap')
}

function editPetName() {
  const name = prompt('給寵物取個暱稱', pet.value.nickname)
  if (name && name.trim()) {
    pet.value = { ...pet.value, nickname: name.trim() }
    savePet(pet.value)
    sfx.play('correct')
  }
}

function changePet() {
  sfx.play('ui_open')
  window.dispatchEvent(new CustomEvent('pandora:pet-change'))
}

async function doLogout() {
  sfx.play('ui_close')
  await logout()
  ent.reset()
  router.push('/login')
}

const deleteConfirmText = ref('')
const deleteLoading = ref(false)
const deleteError = ref<string | null>(null)

// === 資料匯出 ===
const exportBusy = ref<'pdf' | 'csv' | null>(null)
const exportMsg = ref<string | null>(null)

async function doExport(kind: 'pdf' | 'csv') {
  if (exportBusy.value) return
  exportBusy.value = kind
  exportMsg.value = null
  try {
    const res = kind === 'pdf' ? await ExportApi.pdf() : await ExportApi.csv()
    const url = res.data.data.download_url
    sfx.play('correct')
    exportMsg.value = '✓ 已產生下載連結'
    window.open(url, '_blank', 'noopener,noreferrer')
  } catch (e) {
    if (e instanceof PaywallRequiredError) {
      router.push(e.paywallRedirect || '/me/premium')
      return
    }
    exportMsg.value = '匯出失敗，請稍後再試'
    sfx.play('wrong')
  } finally {
    exportBusy.value = null
  }
}

// === 訂閱狀態顯示 ===
const subStatus = computed(() => {
  const d = ent.data
  if (!d) return null
  if (!d.premium) return { kind: 'free' as const }
  // pause 狀態：後端可能用 paused / premium_until 過期等表達；先以 premium_until 計算剩餘天數作呈現
  const until = d.premium_until
  if (until) {
    const ms = new Date(until).getTime() - Date.now()
    const days = Math.max(0, Math.ceil(ms / 86400000))
    return { kind: 'active' as const, until, daysLeft: days, autoRenew: d.auto_renew }
  }
  return { kind: 'active' as const, until: null, daysLeft: null, autoRenew: d.auto_renew }
})

function goCancel() {
  sfx.play('ui_open')
  router.push('/subscription/cancel')
}

async function confirmDeleteData() {
  if (deleteConfirmText.value !== '刪除') {
    deleteError.value = '請輸入「刪除」二字確認'
    return
  }
  deleteLoading.value = true
  deleteError.value = null
  try {
    const result = await deleteCalendarData()
    sfx.play('notify')
    alert('妳的月曆資料已全部清除。\n\n' + (result?.message ?? ''))
    ent.reset()
    router.push('/login')
  } catch (e: any) {
    deleteError.value = e?.response?.data?.error ?? '刪除失敗，請重試或來信 support@js-store.com.tw'
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-6 max-w-md md:max-w-4xl lg:max-w-5xl mx-auto space-y-5 md:space-y-6">
    <header class="text-center space-y-2">
      <div class="w-24 h-24 mx-auto rounded-full bg-peach-gradient flex items-center justify-center text-4xl shadow-soft">
        👤
      </div>
      <p class="font-zen text-xs text-stone-500">{{ greeting }}，</p>
      <h1 class="font-display text-2xl font-bold text-peach-500">{{ user?.display_name ?? user?.name ?? t('profile_greeting_default') }}</h1>
      <p v-if="user?.email" class="text-xs text-stone-400 font-zen">{{ user.email }}</p>
      <p v-else-if="user?.identity_uuid" class="text-[10px] text-stone-300 font-zen tracking-wide">
        ID {{ user.identity_uuid.slice(0, 8) }}
      </p>
      <span
        v-if="ent.isPremium()"
        data-test="premium-badge"
        class="inline-block mt-1 text-[11px] bg-gradient-to-r from-peach-400 to-sakura-400 text-white px-3 py-1 rounded-full font-zen font-semibold shadow-soft"
      >
        💎 Premium
      </span>
    </header>

    <div class="md:grid md:grid-cols-2 md:gap-5 md:items-start space-y-5 md:space-y-0">
    <!-- 我的寵物 -->
    <Card tone="cream" class="text-center space-y-3">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">My Pet</p>
      <div class="flex justify-center">
        <Character
          :species="pet.species"
          :level="level"
          :outfit="pet.outfit"
          mood="happy"
          :size="140"
          :show-rarity="true"
          :show-halo="true"
          :floaty="true"
          :interactive="true"
        />
      </div>
      <button
        @click="editPetName"
        class="font-display text-lg text-peach-500 hover:text-peach-400 transition-colors"
      >
        {{ pet.nickname }} <span class="text-xs text-stone-400">✎</span>
      </button>
      <div class="flex justify-center gap-2 pt-1">
        <button
          @click="changePet"
          data-test="change-pet"
          class="text-[11px] font-zen text-peach-500 bg-white border border-peach-200 px-3 py-1.5 rounded-full hover:bg-peach-50 transition-all active:scale-95"
        >
          🔄 換寵物
        </button>
        <RouterLink
          to="/me/journey"
          class="text-[11px] font-zen text-peach-500 bg-white border border-peach-200 px-3 py-1.5 rounded-full hover:bg-peach-50 transition-all active:scale-95"
        >
          🎀 換 outfit
        </RouterLink>
      </div>
      <p class="font-zen text-xs text-stone-500">
        XP {{ xp }} · 連續記錄會讓寵物升級
      </p>
    </Card>

    <!-- 成就進度條 -->
    <Card v-if="!journeyLoading && journey" tone="plain" class="space-y-3" data-test="achievement-progress">
      <div class="flex items-center justify-between">
        <h3 class="font-display font-bold text-peach-500 text-sm">朵朵旅程</h3>
        <RouterLink to="/me/journey" class="text-[11px] font-zen text-peach-400 hover:text-peach-500">
          看全部 →
        </RouterLink>
      </div>

      <!-- Streak -->
      <div class="flex items-center gap-3">
        <div class="text-3xl">🔥</div>
        <div class="flex-1">
          <p class="font-zen text-[11px] text-stone-500">連用天數</p>
          <p class="font-display font-bold text-peach-500 text-xl leading-none">
            {{ journey.streak_days }} <span class="text-xs text-stone-400 font-zen">天</span>
          </p>
        </div>
        <div class="text-right">
          <p class="font-zen text-[11px] text-stone-500">Level</p>
          <p class="font-display font-bold text-peach-500 text-xl leading-none">{{ journey.level }}</p>
        </div>
      </div>

      <!-- Level 進度 -->
      <div class="space-y-1">
        <div class="flex justify-between text-[11px] font-zen text-stone-500">
          <span>距離 Lv {{ journey.level + 1 }}</span>
          <span>{{ journey.progress_in_level }} / {{ journey.need_for_next_level }} XP</span>
        </div>
        <div class="h-2 rounded-full bg-cream-100 overflow-hidden">
          <div
            class="h-full bg-peach-gradient transition-all duration-500"
            :style="{ width: levelProgressPct + '%' }"
          />
        </div>
      </div>

      <!-- 下一個成就 -->
      <div v-if="nextMilestone" class="bg-cream-50 rounded-2xl p-3 space-y-1.5">
        <div class="flex items-center gap-2">
          <span class="text-xl">{{ nextMilestone.icon }}</span>
          <div class="flex-1">
            <p class="font-zen text-sm text-stone-700">下一個成就：{{ nextMilestone.name }}</p>
            <p class="font-zen text-[11px] text-stone-500">
              {{ nextMilestone.progress ?? 0 }} / {{ nextMilestone.target }}
            </p>
          </div>
        </div>
        <div class="h-1.5 rounded-full bg-white overflow-hidden">
          <div
            class="h-full bg-sakura-300"
            :style="{
              width:
                Math.min(
                  100,
                  Math.round(((nextMilestone.progress ?? 0) / (nextMilestone.target || 1)) * 100),
                ) + '%',
            }"
          />
        </div>
      </div>
    </Card>

    <Card tone="plain" :padded="false" class="overflow-hidden">
      <RouterLink
        to="/me/journey"
        data-test="link-journey"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">✨ 我的旅程</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/bbt"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">🌡️ 基礎體溫</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/partner"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">💞 伴侶分享</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/premium"
        data-test="link-premium"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ ent.isPremium() ? '💎 管理 Premium' : '💎 看看 Premium' }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/week-report"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">📰 每週朵朵報告</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/pms"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">🌙 PMS 模式分析</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <!--
        🔒 紅線：婕樂纖會員入口只在這層深層出現，且後端 ProductLinkResolver gate 通過才會
        實際顯示內容。對未綁母艦 / 未付費用戶完全不顯示商品 — 入口仍可點，但內頁會顯示
        「妳還沒開通」。
      -->
      <RouterLink
        to="/me/jerosse"
        data-test="link-jerosse"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">婕樂纖會員</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </Card>

    <!-- 設定 -->
    <Card tone="plain" class="space-y-3">
      <h3 class="font-display font-bold text-peach-500 text-sm">設定</h3>
      <label class="flex items-center justify-between cursor-pointer">
        <div>
          <p class="font-zen text-sm text-stone-700">音效</p>
          <p class="font-zen text-[11px] text-stone-400 mt-0.5">朵朵的提示音與動畫音效</p>
        </div>
        <button
          @click="toggleMute"
          data-test="sfx-toggle"
          class="relative w-12 h-7 rounded-full transition-colors shrink-0"
          :class="muted ? 'bg-stone-300' : 'bg-peach-400'"
          :aria-pressed="!muted"
        >
          <span
            class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform"
            :class="muted ? '' : 'translate-x-5'"
          />
        </button>
      </label>

      <label
        v-if="pushState.supported"
        class="flex items-center justify-between cursor-pointer pt-2 border-t border-cream-100"
      >
        <div>
          <p class="font-zen text-sm text-stone-700">通知</p>
          <p class="font-zen text-[11px] text-stone-400 mt-0.5">經期前一天 / 排卵期 朵朵會提醒妳</p>
        </div>
        <button
          @click="togglePush"
          :disabled="pushBusy"
          data-test="push-toggle"
          class="relative w-12 h-7 rounded-full transition-colors shrink-0 disabled:opacity-50"
          :class="pushEnabled ? 'bg-peach-400' : 'bg-stone-300'"
          :aria-pressed="pushEnabled"
        >
          <span
            class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform"
            :class="pushEnabled ? 'translate-x-5' : ''"
          />
        </button>
      </label>
      <p v-if="pushMessage" class="font-zen text-[11px] text-stone-500">{{ pushMessage }}</p>
    </Card>

    <!-- 個人化（inclusive mode toggle） -->
    <Card tone="plain" class="space-y-3" data-test="personalize-card">
      <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('setting_personalize') }}</h3>
      <label class="flex items-start justify-between cursor-pointer gap-3">
        <div class="flex-1 pr-2">
          <p class="font-zen text-sm text-stone-700">{{ t('setting_inclusive_label') }}</p>
          <p class="font-zen text-[11px] text-stone-500 mt-1 leading-relaxed">
            {{ t('setting_inclusive_help') }}
          </p>
        </div>
        <button
          type="button"
          data-test="inclusive-toggle"
          class="relative w-12 h-7 rounded-full transition-colors shrink-0"
          :class="inclusiveMode ? 'bg-peach-400' : 'bg-stone-300'"
          :aria-pressed="inclusiveMode"
          @click="toggleInclusive"
        >
          <span
            class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform"
            :class="inclusiveMode ? 'translate-x-5' : ''"
          />
        </button>
      </label>
    </Card>

    <!-- 安全與隱私 -->
    <Card v-if="isNative" tone="cream" class="space-y-3" data-test="security-card">
      <h3 class="font-display font-bold text-peach-500 text-sm">安全與隱私</h3>

      <label
        class="flex items-center justify-between"
        :class="biometricInfo.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
      >
        <div class="pr-3">
          <p class="font-zen text-sm text-stone-700">開啟 Face ID / 指紋鎖</p>
          <p class="font-zen text-[11px] text-stone-500 mt-1 leading-relaxed">
            鎖定後，App 進入背景超過 30 秒會要求重新驗證。妳的資料只會在妳的裝置上加密。
          </p>
        </div>
        <button
          :disabled="!biometricInfo.available || lockBusy"
          data-test="lock-toggle"
          class="relative w-12 h-7 rounded-full transition-colors shrink-0 disabled:opacity-50"
          :class="lockEnabled ? 'bg-peach-400' : 'bg-stone-300'"
          :aria-pressed="lockEnabled"
          @click="toggleLock"
        >
          <span
            class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform"
            :class="lockEnabled ? 'translate-x-5' : ''"
          />
        </button>
      </label>

      <p
        v-if="lockUnsupportedHint"
        class="font-zen text-[11px] text-sakura-500"
        data-test="lock-unsupported-hint"
      >
        {{ lockUnsupportedHint }}
      </p>
      <p v-if="lockMessage" class="font-zen text-[11px] text-stone-500">{{ lockMessage }}</p>

    </Card>

    <!-- 資料匯出（Premium，所有平台都顯示）-->
    <Card tone="plain" class="space-y-3" data-test="export-card">
      <div>
        <h3 class="font-display font-bold text-peach-500 text-sm">資料匯出</h3>
        <p class="font-zen text-[11px] text-stone-500 leading-relaxed mt-1">
          匯出妳的完整週期 / 症狀紀錄，可以分享給醫師參考。Premium 功能。
        </p>
      </div>
      <div class="flex gap-2">
        <Button
          size="sm"
          variant="secondary"
          :loading="exportBusy === 'pdf'"
          :disabled="!!exportBusy"
          data-test="export-pdf"
          @click="doExport('pdf')"
        >
          📄 PDF
        </Button>
        <Button
          size="sm"
          variant="secondary"
          :loading="exportBusy === 'csv'"
          :disabled="!!exportBusy"
          data-test="export-csv"
          @click="doExport('csv')"
        >
          📊 CSV
        </Button>
      </div>
      <p v-if="exportMsg" class="font-zen text-[11px] text-stone-500">{{ exportMsg }}</p>
    </Card>

    <!-- 我的訂閱 -->
    <Card v-if="subStatus" tone="plain" class="space-y-3" data-test="subscription-card">
      <div class="flex items-center justify-between">
        <h3 class="font-display font-bold text-peach-500 text-sm">我的訂閱</h3>
        <span
          v-if="subStatus.kind === 'active'"
          class="text-[10px] font-zen bg-peach-100 text-peach-600 px-2 py-0.5 rounded-full"
        >
          進行中
        </span>
        <span
          v-else
          class="text-[10px] font-zen bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full"
        >
          免費版
        </span>
      </div>

      <template v-if="subStatus.kind === 'active'">
        <div class="bg-cream-50 rounded-2xl p-3 space-y-1">
          <p class="font-zen text-[11px] text-stone-500">下次續訂 / 到期</p>
          <p class="font-zen text-sm text-stone-700">
            {{ subStatus.until ? new Date(subStatus.until).toLocaleDateString('zh-TW') : '—' }}
            <span v-if="subStatus.daysLeft !== null" class="text-stone-400 text-[11px]">
              （還有 {{ subStatus.daysLeft }} 天）
            </span>
          </p>
          <p class="font-zen text-[11px] text-stone-500 pt-1">
            自動續訂：{{ subStatus.autoRenew ? '開啟' : '關閉' }}
          </p>
        </div>
        <Button variant="ghost" size="sm" full data-test="cancel-subscription" @click="goCancel">
          取消訂閱
        </Button>
      </template>

      <template v-else>
        <p class="font-zen text-xs text-stone-500 leading-relaxed">
          升級到 Premium 解鎖：年度回顧 / 資料匯出 / PMS 模式分析 / 朵朵深度建議
        </p>
        <Button size="sm" full @click="router.push('/me/premium')">看看 Premium</Button>
      </template>
    </Card>

    <!-- 幫助 -->
    <Card tone="plain" :padded="false" class="overflow-hidden" data-test="help-card">
      <div class="px-5 py-3 border-b border-cream-100">
        <h3 class="font-display font-bold text-peach-500 text-sm">幫助</h3>
      </div>
      <RouterLink
        to="/faq"
        data-test="link-faq"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">💡 常見問題</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/health-check"
        data-test="link-health-check"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">🌸 身體狀況自我評估</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/feedback"
        data-test="link-feedback"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">💌 給朵朵的話</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </Card>

    <Card tone="plain" class="space-y-2 text-sm">
      <h2 class="font-display font-bold text-peach-500">關於潘朵拉月曆</h2>
      <p class="text-stone-600 leading-relaxed font-zen text-sm">
        妳的週期資料只屬於妳。Phase 0 demo 階段資料僅在本機 SQLite，正式版上架後走集團 Pandora Core 統一帳號，朵朵會跨 App 陪伴妳。
      </p>
      <p class="text-stone-500 text-[11px] font-zen">❌ 不做廣告 · ❌ 不賣資料 · ✅ 妳隨時可以刪除帳號</p>
    </Card>

    </div>

    <Button variant="secondary" full data-test="logout" sfx="ui_close" @click="doLogout">登出</Button>

    <!-- App Store / GDPR：刪除我的月曆資料 -->
    <Card tone="plain" class="space-y-3 border border-sakura-200">
      <details>
        <summary class="cursor-pointer font-display text-sm text-sakura-500 font-bold flex items-center justify-between">
          <span>🗑 刪除我的月曆資料</span>
          <span class="text-xs text-stone-400">展開</span>
        </summary>
        <div class="mt-3 space-y-2.5 text-[12px] text-stone-600 font-zen leading-relaxed">
          <p>這會清除妳在月曆累積的所有資料：週期 / 症狀 / 朵朵 check-in / 寵物進度 / 訂閱狀態。</p>
          <p class="text-stone-500 text-[11px]">
            ⚠️ 集團帳號（FP 統一身份）保留在 Pandora Core，若要連集團帳號一起清除請另外來信
            <a href="mailto:support@js-store.com.tw" class="text-peach-500 underline">support@js-store.com.tw</a>
          </p>
          <input
            v-model="deleteConfirmText"
            placeholder='輸入「刪除」二字確認'
            class="w-full px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-sakura-300 text-sm font-zen"
            data-test="delete-confirm-input"
          />
          <p v-if="deleteError" class="text-xs text-sakura-500 font-zen">{{ deleteError }}</p>
          <button
            @click="confirmDeleteData"
            :disabled="deleteLoading"
            data-test="delete-account-confirm"
            class="w-full py-2.5 rounded-2xl bg-sakura-400 hover:bg-sakura-500 disabled:opacity-50 text-white font-zen text-sm transition-all"
          >
            {{ deleteLoading ? '清除中…' : '清除全部月曆資料' }}
          </button>
        </div>
      </details>
    </Card>

    <div class="flex justify-center gap-4 text-[11px] text-stone-400 pt-2 font-zen">
      <RouterLink to="/privacy" class="hover:text-peach-500 transition-colors">隱私權</RouterLink>
      <span>·</span>
      <RouterLink to="/terms" class="hover:text-peach-500 transition-colors">使用條款</RouterLink>
      <span>·</span>
      <a href="mailto:support@js-store.com.tw" class="hover:text-peach-500 transition-colors">客服</a>
    </div>

    <!-- 隱私強化 -->
    <Card tone="cream" class="space-y-1.5 text-center">
      <p class="font-display font-bold text-peach-500 text-base">妳的資料只屬於妳</p>
      <p class="font-zen text-[12px] text-stone-600 leading-relaxed">
        我們不賣資料、不放廣告。妳隨時可以匯出或刪除妳的紀錄。
      </p>
    </Card>
  </div>
</template>
