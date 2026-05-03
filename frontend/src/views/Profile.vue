<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getStoredUser, logout, deleteCalendarData } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import { pushSupport, enablePush, disablePush, listSubscriptions, sendTestPush } from '../lib/push'
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
import { useI18n, type Locale } from '../composables/useI18n'

const router = useRouter()
const user = getStoredUser()
const ent = useEntitlementsStore()
const sfx = useSfx()
const inclusiveMode = useInclusiveMode()
const { t } = useTone()
const { locale: currentLocale } = useI18n()

function onLocaleChange(e: Event) {
  const val = (e.target as HTMLSelectElement).value as Locale
  currentLocale.value = val
  sfx.play('ui_tap')
}

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
const pushDeviceCount = ref(0)
const pushPlatformLabel = computed(() => {
  switch (pushState.value.platform) {
    case 'ios': return 'iOS'
    case 'android': return 'Android'
    default: return 'Web'
  }
})

async function refreshPushDeviceCount() {
  try {
    const subs = await listSubscriptions()
    pushDeviceCount.value = subs.length
  } catch {
    pushDeviceCount.value = 0
  }
}

async function togglePush() {
  pushBusy.value = true
  pushMessage.value = null
  if (pushEnabled.value) {
    await disablePush()
    pushEnabled.value = false
    pushMessage.value = t('profile_push_disabled')
  } else {
    const r = await enablePush()
    if (r.ok) {
      pushEnabled.value = true
      pushMessage.value = t('profile_push_enabled')
    } else {
      pushMessage.value = t('profile_push_error_prefix') + (r.error ?? t('profile_push_error_default'))
    }
  }
  await refreshPushDeviceCount()
  pushBusy.value = false
}

async function sendPushTest() {
  pushBusy.value = true
  try {
    const r = await sendTestPush()
    pushMessage.value = `已對 ${r.count} 個裝置送出測試訊息`
  } catch {
    pushMessage.value = '送出測試訊息失敗'
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
      return t('profile_lock_unsupported_not_enrolled')
    case 'no_hardware':
      return t('profile_lock_unsupported_no_hardware')
    default:
      return t('profile_lock_unsupported_other')
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
      const ok = await verify(t('profile_lock_verify_on_label'))
      if (!ok) {
        lockMessage.value = t('profile_lock_verify_failed_on')
        lockEnabled.value = false
        return
      }
      setLockEnabled(true)
      lockEnabled.value = true
      lockMessage.value = t('profile_lock_enabled_msg')
      sfx.play('correct')
    } else {
      // 關閉前也驗證一次（避免別人拿到手機就關掉）
      const ok = await verify(t('profile_lock_verify_off_label'))
      if (!ok) {
        lockMessage.value = t('profile_lock_verify_failed_off')
        lockEnabled.value = true
        return
      }
      setLockEnabled(false)
      lockEnabled.value = false
      lockMessage.value = t('profile_lock_disabled_msg')
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
  refreshPushDeviceCount()
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 6) return t('profile_evening')
  if (h < 12) return t('profile_morning')
  if (h < 18) return t('profile_noon')
  return t('profile_evening')
})

function toggleMute() {
  muted.value = sfx.toggle()
  if (!muted.value) sfx.play('ui_tap')
}

function editPetName() {
  const name = prompt(t('profile_pet_name_prompt'), pet.value.nickname)
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
    exportMsg.value = t('profile_export_success')
    window.open(url, '_blank', 'noopener,noreferrer')
  } catch (e) {
    if (e instanceof PaywallRequiredError) {
      router.push(e.paywallRedirect || '/me/premium')
      return
    }
    exportMsg.value = t('profile_export_failed')
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
  if (deleteConfirmText.value !== '刪除' && deleteConfirmText.value !== 'DELETE') {
    deleteError.value = t('profile_delete_must_match')
    return
  }
  deleteLoading.value = true
  deleteError.value = null
  try {
    const result = await deleteCalendarData()
    sfx.play('notify')
    alert(t('profile_delete_done_alert') + '\n\n' + (result?.message ?? ''))
    ent.reset()
    router.push('/login')
  } catch (e: any) {
    deleteError.value = e?.response?.data?.error ?? t('profile_delete_failed')
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-6 max-w-md md:max-w-4xl lg:max-w-5xl mx-auto space-y-5 md:space-y-6">
    <header class="text-center space-y-2">
      <div
        class="w-24 h-24 mx-auto rounded-full bg-peach-gradient flex items-center justify-center text-4xl shadow-soft"
        role="img"
        :aria-label="t('profile_avatar_alt')"
      >
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
          {{ t('profile_pet_change_btn') }}
        </button>
        <RouterLink
          to="/me/journey"
          class="text-[11px] font-zen text-peach-500 bg-white border border-peach-200 px-3 py-1.5 rounded-full hover:bg-peach-50 transition-all active:scale-95"
        >
          {{ t('profile_pet_outfit_btn') }}
        </RouterLink>
      </div>
      <p class="font-zen text-xs text-stone-500">
        {{ t('profile_pet_xp_hint', { xp }) }}
      </p>
    </Card>

    <!-- 成就進度條 -->
    <Card v-if="!journeyLoading && journey" tone="plain" class="space-y-3" data-test="achievement-progress">
      <div class="flex items-center justify-between">
        <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_journey_title') }}</h3>
        <RouterLink to="/me/journey" class="text-[11px] font-zen text-peach-400 hover:text-peach-500">
          {{ t('profile_journey_view_all') }}
        </RouterLink>
      </div>

      <!-- Streak -->
      <div class="flex items-center gap-3">
        <div class="text-3xl">🔥</div>
        <div class="flex-1">
          <p class="font-zen text-[11px] text-stone-500">{{ t('profile_streak_label') }}</p>
          <p class="font-display font-bold text-peach-500 text-xl leading-none">
            {{ journey.streak_days }} <span class="text-xs text-stone-400 font-zen">{{ t('profile_streak_days') }}</span>
          </p>
        </div>
        <div class="text-right">
          <p class="font-zen text-[11px] text-stone-500">{{ t('profile_level_label') }}</p>
          <p class="font-display font-bold text-peach-500 text-xl leading-none">{{ journey.level }}</p>
        </div>
      </div>

      <!-- Level 進度 -->
      <div class="space-y-1">
        <div class="flex justify-between text-[11px] font-zen text-stone-500">
          <span>{{ t('profile_level_to_next', { lv: journey.level + 1 }) }}</span>
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
            <p class="font-zen text-sm text-stone-700">{{ t('profile_next_milestone_prefix') }}{{ nextMilestone.name }}</p>
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
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_journey') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/bbt"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_bbt') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/partner"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_partner') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/premium"
        data-test="link-premium"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ ent.isPremium() ? t('profile_link_premium_manage') : t('profile_link_premium_view') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/week-report"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_week_report') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/me/pms"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100 last:border-b-0"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_pms') }}</span>
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
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_jerosse') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </Card>

    <!-- 設定 -->
    <Card tone="plain" class="space-y-3">
      <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_settings_title') }}</h3>
      <label class="flex items-center justify-between cursor-pointer">
        <div>
          <p class="font-zen text-sm text-stone-700">{{ t('profile_setting_sfx_label') }}</p>
          <p class="font-zen text-[11px] text-stone-400 mt-0.5">{{ t('profile_setting_sfx_help') }}</p>
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
          <p class="font-zen text-sm text-stone-700">{{ t('profile_setting_push_label') }}</p>
          <p class="font-zen text-[11px] text-stone-400 mt-0.5">{{ t('profile_setting_push_help') }}</p>
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
      <div
        v-if="pushState.supported"
        class="flex items-center justify-between text-[11px] text-stone-400 font-zen pt-1"
        data-test="push-device-info"
      >
        <span>目前裝置：{{ pushPlatformLabel }}</span>
        <span>已註冊 {{ pushDeviceCount }} 個裝置</span>
      </div>
      <button
        v-if="pushState.supported && pushEnabled && pushDeviceCount > 0"
        @click="sendPushTest"
        :disabled="pushBusy"
        data-test="push-test"
        class="font-zen text-[11px] text-peach-500 underline self-start disabled:opacity-50"
      >
        送出一條測試訊息
      </button>
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

      <!-- Locale switcher (early access, foundation only — most views still zh-TW) -->
      <div class="pt-3 border-t border-stone-200/60" data-test="locale-switcher">
        <label class="block">
          <p class="font-zen text-sm text-stone-700">{{ t('profile_locale_label') }}</p>
          <select
            :value="currentLocale"
            data-test="locale-select"
            class="mt-2 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 font-zen text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-peach-300"
            @change="onLocaleChange"
          >
            <option value="zh-TW">繁體中文</option>
            <option value="en">English</option>
          </select>
          <p class="font-zen text-[11px] text-stone-500 mt-1 leading-relaxed">
            {{ t('profile_locale_help') }}
          </p>
        </label>
      </div>
    </Card>

    <!-- 安全與隱私 -->
    <Card v-if="isNative" tone="cream" class="space-y-3" data-test="security-card">
      <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_security_title') }}</h3>

      <label
        class="flex items-center justify-between"
        :class="biometricInfo.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'"
      >
        <div class="pr-3">
          <p class="font-zen text-sm text-stone-700">{{ t('profile_lock_label') }}</p>
          <p class="font-zen text-[11px] text-stone-500 mt-1 leading-relaxed">
            {{ t('profile_lock_help') }}
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
        <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_export_title') }}</h3>
        <p class="font-zen text-[11px] text-stone-500 leading-relaxed mt-1">
          {{ t('profile_export_help') }}
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
        <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_subscription_title') }}</h3>
        <span
          v-if="subStatus.kind === 'active'"
          class="text-[10px] font-zen bg-peach-100 text-peach-600 px-2 py-0.5 rounded-full"
        >
          {{ t('profile_sub_active_pill') }}
        </span>
        <span
          v-else
          class="text-[10px] font-zen bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full"
        >
          {{ t('profile_sub_free_pill') }}
        </span>
      </div>

      <template v-if="subStatus.kind === 'active'">
        <div class="bg-cream-50 rounded-2xl p-3 space-y-1">
          <p class="font-zen text-[11px] text-stone-500">{{ t('profile_sub_next_billing') }}</p>
          <p class="font-zen text-sm text-stone-700">
            {{ subStatus.until ? new Date(subStatus.until).toLocaleDateString('zh-TW') : '—' }}
            <span v-if="subStatus.daysLeft !== null" class="text-stone-400 text-[11px]">
              {{ t('profile_sub_days_left', { days: subStatus.daysLeft }) }}
            </span>
          </p>
          <p class="font-zen text-[11px] text-stone-500 pt-1">
            {{ t('profile_sub_auto_renew', { state: subStatus.autoRenew ? t('profile_sub_auto_on') : t('profile_sub_auto_off') }) }}
          </p>
        </div>
        <Button variant="ghost" size="sm" full data-test="cancel-subscription" @click="goCancel">
          {{ t('profile_sub_cancel_btn') }}
        </Button>
      </template>

      <template v-else>
        <p class="font-zen text-xs text-stone-500 leading-relaxed">
          {{ t('profile_sub_upgrade_blurb') }}
        </p>
        <Button size="sm" full @click="router.push('/me/premium')">{{ t('profile_link_premium_view') }}</Button>
      </template>
    </Card>

    <!-- 幫助 -->
    <Card tone="plain" :padded="false" class="overflow-hidden" data-test="help-card">
      <div class="px-5 py-3 border-b border-cream-100">
        <h3 class="font-display font-bold text-peach-500 text-sm">{{ t('profile_help_title') }}</h3>
      </div>
      <RouterLink
        to="/faq"
        data-test="link-faq"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_faq') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/health-check"
        data-test="link-health-check"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors border-b border-cream-100"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_health_check') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
      <RouterLink
        to="/feedback"
        data-test="link-feedback"
        class="flex items-center justify-between px-5 py-4 hover:bg-peach-50 transition-colors"
        @click="sfx.play('ui_tap')"
      >
        <span class="font-zen text-peach-500 text-sm">{{ t('profile_link_feedback') }}</span>
        <span class="text-stone-400">→</span>
      </RouterLink>
    </Card>

    <Card tone="plain" class="space-y-2 text-sm">
      <h2 class="font-display font-bold text-peach-500">{{ t('profile_section_about') }}</h2>
      <p class="text-stone-600 leading-relaxed font-zen text-sm">
        {{ t('profile_about_blurb') }}
      </p>
      <p class="text-stone-500 text-[11px] font-zen">{{ t('profile_no_ads_line') }}</p>
    </Card>

    </div>

    <Button variant="secondary" full data-test="logout" sfx="ui_close" @click="doLogout">{{ t('profile_logout') }}</Button>

    <!-- App Store / GDPR：刪除我的月曆資料 -->
    <Card tone="plain" class="space-y-3 border border-sakura-200">
      <details>
        <summary class="cursor-pointer font-display text-sm text-sakura-500 font-bold flex items-center justify-between">
          <span>{{ t('profile_delete_summary') }}</span>
          <span class="text-xs text-stone-400">{{ t('profile_delete_expand') }}</span>
        </summary>
        <div class="mt-3 space-y-2.5 text-[12px] text-stone-600 font-zen leading-relaxed">
          <p>{{ t('profile_delete_blurb') }}</p>
          <p class="text-stone-500 text-[11px]">
            {{ t('profile_delete_identity_note') }}
            <a href="mailto:support@js-store.com.tw" class="text-peach-500 underline">support@js-store.com.tw</a>
          </p>
          <label for="delete-confirm-input" class="sr-only">{{ t('profile_delete_confirm_label') }}</label>
          <input
            id="delete-confirm-input"
            v-model="deleteConfirmText"
            :placeholder="t('profile_delete_confirm_label')"
            :aria-label="t('profile_delete_confirm_aria')"
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
            {{ deleteLoading ? t('profile_delete_loading') : t('profile_delete_btn') }}
          </button>
        </div>
      </details>
    </Card>

    <div class="flex justify-center gap-4 text-[11px] text-stone-400 pt-2 font-zen">
      <RouterLink to="/privacy" class="hover:text-peach-500 transition-colors">{{ t('profile_link_privacy') }}</RouterLink>
      <span>·</span>
      <RouterLink to="/terms" class="hover:text-peach-500 transition-colors">{{ t('profile_link_terms') }}</RouterLink>
      <span>·</span>
      <a href="mailto:support@js-store.com.tw" class="hover:text-peach-500 transition-colors">{{ t('profile_link_support') }}</a>
    </div>

    <!-- 隱私強化 -->
    <Card tone="cream" class="space-y-1.5 text-center">
      <p class="font-display font-bold text-peach-500 text-base">{{ t('profile_privacy_card_title') }}</p>
      <p class="font-zen text-[12px] text-stone-600 leading-relaxed">
        {{ t('profile_privacy_card_blurb') }}
      </p>
    </Card>
  </div>
</template>
