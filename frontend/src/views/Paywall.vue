<script setup lang="ts">
/**
 * Paywall — freemium funnel narrative 文案重寫版（v2）。
 *
 * 結構：Hero(emotional + sunk-cost) → trial hint → 5 v2 benefits →
 *       free_forever_promise（核心差異化）→ 2 plan cards → social proof →
 *       privacy promise → restore + legal → 黏底 CTA。
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  SubscriptionApi,
  JourneyApi,
  AchievementsApi,
  type SubscriptionProduct,
  type JourneyData,
} from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import { useTrial } from '../composables/useTrial'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const router = useRouter()
const ent = useEntitlementsStore()
const trial = useTrial()

const products = ref<SubscriptionProduct[]>([])
const features = ref<string[]>([])
const journey = ref<JourneyData | null>(null)
const achievementsCount = ref<number | null>(null)
const loading = ref(false)
const initialLoad = ref(true)
const restoring = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const selectedId = ref<string | null>(null)

onMounted(async () => {
  ent.load().catch(() => {})
  const tasks: Promise<unknown>[] = [
    SubscriptionApi.products()
      .then((res) => {
        products.value = res.data.data
        features.value = res.data.features
        selectedId.value =
          res.data.data.find((p) => p.id.endsWith('annual'))?.id
          ?? res.data.data[0]?.id
          ?? null
      })
      .catch(() => {}),
    JourneyApi.show()
      .then((res) => { journey.value = res.data.data })
      .catch(() => {}),
    AchievementsApi.list()
      .then((res) => { achievementsCount.value = res.data.data.unlocked_count })
      .catch(() => {}),
  ]
  try {
    await Promise.allSettled(tasks)
  } finally {
    initialLoad.value = false
  }
})

const annualProduct = computed(() => products.value.find((p) => p.id.endsWith('annual')))
const selectedProduct = computed(() => products.value.find((p) => p.id === selectedId.value))

const daysUsed = computed(() => journey.value?.streak_days ?? 0)
const recordsCount = computed(() => {
  const j = journey.value?.last_30_days
  if (!j) return 0
  return (j.cycles_logged ?? 0) + (j.symptoms_logged ?? 0) + (j.dodo_checkins ?? 0)
})
const achievementsUnlocked = computed(() => achievementsCount.value ?? 0)

const heroSubtitle = computed(() => {
  if (daysUsed.value > 0 || recordsCount.value > 0 || achievementsUnlocked.value > 0) {
    return t('paywall_hero_subtitle', {
      days_used: daysUsed.value,
      records_count: recordsCount.value,
      achievements_count: achievementsUnlocked.value,
    })
  }
  return t('paywall_hero_subtitle_no_data')
})

const PREMIUM_BENEFITS = computed<Array<{ emoji: string; title: string; desc: string }>>(() => [
  { emoji: '🌙', title: t('paywall_v2_benefit_1_title'), desc: t('paywall_v2_benefit_1_body') },
  { emoji: '📖', title: t('paywall_v2_benefit_2_title'), desc: t('paywall_v2_benefit_2_body') },
  { emoji: '📸', title: t('paywall_v2_benefit_3_title'), desc: t('paywall_v2_benefit_3_body') },
  { emoji: '💬', title: t('paywall_v2_benefit_4_title'), desc: t('paywall_v2_benefit_4_body') },
  { emoji: '✨', title: t('paywall_v2_benefit_5_title'), desc: t('paywall_v2_benefit_5_body') },
])

const benefits = computed(() => {
  if (features.value.length >= 3) {
    return features.value.map((f, idx) => ({
      emoji: ['🌙', '📖', '📸', '💬', '✨', '🌸'][idx] ?? '✨',
      title: f,
      desc: '',
    }))
  }
  return PREMIUM_BENEFITS.value
})

async function ecpayCheckout() {
  if (!selectedProduct.value) return
  loading.value = true
  error.value = null
  try {
    const returnUrl = `${window.location.origin}${window.location.pathname}#/me?ecpay=ok`
    const { data } = await SubscriptionApi.ecpayCheckout(selectedProduct.value.id, returnUrl)
    if (data?.data?.action_url) {
      message.value = t('paywall_msg_ecpay_ready')
      window.alert(
        `Demo Phase: ECPay checkout 觸發\n\naction_url: ${data.data.action_url}\n\nProd 會自動跳轉到 ECPay 完成付款。`,
      )
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('paywall_error_checkout_fail')
  } finally {
    loading.value = false
  }
}

async function restorePurchase() {
  restoring.value = true
  error.value = null
  message.value = null
  try {
    await ent.load()
    if (ent.isPremium()) {
      message.value = t('paywall_msg_restored')
    } else {
      message.value = t('paywall_msg_no_active_sub')
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('paywall_error_restore_fail')
  } finally {
    restoring.value = false
  }
}

function back() {
  router.push('/me')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-peach-50 via-sakura-50 to-cream-50">
    <div class="px-5 md:px-8 pt-6 pb-32 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-6">
      <button
        @click="back"
        data-test="paywall-back"
        class="font-zen text-sm text-peach-500 hover:text-peach-400"
      >
        {{ t('paywall_btn_back') }}
      </button>

      <header class="text-center space-y-3 pt-2" data-test="paywall-hero">
        <div class="flex justify-center relative">
          <div class="absolute inset-0 bg-gradient-radial from-peach-200/40 to-transparent blur-2xl" aria-hidden="true" />
          <Character
            species="dodo"
            mood="cheering"
            outfit="fp_crown"
            :level="20"
            :size="160"
            :show-halo="true"
            :floaty="true"
          />
        </div>
        <p class="font-zen text-[11px] text-peach-500/70 tracking-[0.3em] uppercase">{{ t('paywall_eyebrow_premium') }}</p>
        <h1 class="font-display text-[28px] md:text-4xl font-black text-peach-500 leading-tight px-4">
          {{ t('paywall_hero_title') }}
        </h1>
        <p class="font-zen text-sm text-stone-600 leading-relaxed max-w-sm mx-auto px-2">
          {{ heroSubtitle }}
        </p>
        <p
          v-if="trial.isInTrial.value && (trial.daysRemaining.value ?? 0) > 0"
          class="font-zen text-[11px] text-peach-500/80 px-3"
          data-test="paywall-trial-hint"
        >
          {{ t('paywall_trial_in_progress_hint', { days: trial.daysRemaining.value ?? 0 }) }}
        </p>
      </header>

      <Spinner v-if="initialLoad" :label="t('paywall_loading')" />

      <Card
        v-else-if="ent.isPremium()"
        tone="peach"
        data-test="already-premium"
        class="text-center text-white space-y-1"
      >
        <p class="font-display font-bold text-lg">{{ t('paywall_already_premium_heading') }}</p>
        <p v-if="ent.data?.premium_until" class="text-xs opacity-90 font-zen">
          {{ t('paywall_already_expires_prefix') }}{{ ent.data.premium_until.slice(0, 10) }}
        </p>
        <button
          type="button"
          data-test="restore-purchase-already"
          :disabled="restoring"
          class="mt-2 px-4 py-1.5 rounded-full bg-white/20 text-xs font-zen hover:bg-white/30 transition-colors disabled:opacity-50"
          @click="restorePurchase"
        >
          {{ restoring ? t('paywall_btn_refresh_checking') : t('paywall_btn_refresh_status') }}
        </button>
      </Card>

      <template v-else>
        <section class="space-y-3" data-test="premium-benefits">
          <h3 class="font-display font-bold text-stone-700 text-lg text-center">
            {{ t('paywall_section_unlock') }}
          </h3>
          <div class="space-y-2.5">
            <div
              v-for="(b, idx) in benefits"
              :key="idx"
              class="flex gap-3.5 items-start bg-white/70 backdrop-blur-sm rounded-2xl p-4 shadow-soft"
            >
              <div class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-peach-100 to-sakura-100 flex items-center justify-center text-2xl">
                {{ b.emoji }}
              </div>
              <div class="flex-1 min-w-0 pt-0.5">
                <p class="font-display font-bold text-stone-700 text-[15px] leading-snug">{{ b.title }}</p>
                <p v-if="b.desc" class="font-zen text-xs text-stone-500 mt-1 leading-relaxed">
                  {{ b.desc }}
                </p>
              </div>
            </div>
          </div>
        </section>

        <Card
          tone="plain"
          data-test="paywall-free-forever-promise"
          class="text-center space-y-2 bg-gradient-to-br from-sage-50 to-cream-50 ring-1 ring-sage-200/50"
        >
          <p class="font-zen text-[11px] text-sage-500/80 tracking-wider">
            {{ t('paywall_free_forever_badge') }}
          </p>
          <p class="font-display font-bold text-sage-600 text-base">
            {{ t('paywall_free_forever_title') }}
          </p>
          <p class="font-zen text-[13px] text-stone-600 leading-relaxed px-2">
            {{ t('paywall_free_forever_body') }}
          </p>
          <p class="font-zen text-[11px] text-sage-500/80 px-2 leading-relaxed">
            {{ t('paywall_free_forever_sub') }}
          </p>
        </Card>

        <section class="space-y-2" data-test="paywall-plans">
          <p class="font-display font-bold text-stone-700 text-lg text-center mb-1">
            {{ t('paywall_section_plan_pick') }}
          </p>
          <div class="grid grid-cols-2 gap-3">
            <button
              v-for="p in products"
              :key="p.id"
              type="button"
              :data-test="`plan-${p.id}`"
              :disabled="loading"
              class="relative bg-white rounded-3xl p-4 text-left transition-all active:scale-[0.98] disabled:opacity-50"
              :class="
                selectedId === p.id
                  ? 'border-2 border-peach-400 ring-4 ring-peach-100 shadow-soft'
                  : 'border border-cream-200 hover:border-peach-200'
              "
              @click="selectedId = p.id"
            >
              <span
                v-if="p === annualProduct"
                class="absolute -top-2.5 left-1/2 -translate-x-1/2 bg-gradient-to-r from-peach-400 to-sakura-400 text-white text-[10px] font-zen font-bold px-3 py-1 rounded-full shadow-soft whitespace-nowrap"
              >
                {{ t('paywall_plan_yearly_badge') }}
              </span>
              <p class="font-display font-bold text-stone-700 text-sm">{{ p.title }}</p>
              <div class="mt-2 flex items-baseline gap-0.5">
                <span class="font-zen text-xs text-stone-400">NT$</span>
                <span class="font-display font-black text-2xl text-peach-500 leading-none">{{ p.price_twd }}</span>
              </div>
              <p class="font-zen text-[11px] text-stone-500 mt-1">
                / {{ p.period === 'year' ? t('paywall_unit_year') : t('paywall_unit_month') }}
              </p>
              <p
                v-if="p.monthly_equivalent"
                class="text-[10px] text-stone-400 mt-1 font-zen"
              >
                {{ t('paywall_monthly_equivalent', { amount: p.monthly_equivalent }) }}
              </p>
              <span
                v-if="p.discount"
                class="mt-2 inline-block text-[10px] bg-sakura-100 text-sakura-500 font-zen font-bold px-2 py-0.5 rounded-full"
              >{{ p.discount }}</span>
            </button>
          </div>
        </section>

        <Card tone="plain" class="text-center space-y-1.5 bg-white/60">
          <div class="flex justify-center gap-1 text-peach-400 text-sm">
            <span v-for="i in 5" :key="i">★</span>
          </div>
          <p class="font-zen text-xs text-stone-500">
            {{ t('paywall_social_proof') }}
          </p>
        </Card>

        <p v-if="message" class="text-center text-peach-500 text-sm font-zen">{{ message }}</p>
        <p v-if="error" class="text-center text-sakura-500 text-sm font-zen">{{ error }}</p>

        <Card tone="plain" class="text-center space-y-1 bg-sage-50/60 border border-sage-100">
          <p class="font-display font-bold text-sage-500 text-sm">🔒 {{ t('paywall_privacy_promise_title') }}</p>
          <p class="font-zen text-[12px] text-stone-600 leading-relaxed px-2">
            {{ t('paywall_footer_no_ads') }}
          </p>
        </Card>

        <div class="space-y-2 pt-1">
          <button
            type="button"
            data-test="restore-purchase"
            :disabled="restoring"
            class="w-full py-2.5 rounded-2xl bg-white/70 border border-cream-200 text-stone-500 font-zen text-sm transition-all active:scale-[0.99] hover:bg-white disabled:opacity-50"
            @click="restorePurchase"
          >
            {{ restoring ? t('paywall_btn_restore_loading') : t('paywall_btn_restore') }}
          </button>

          <p class="font-zen text-[10px] text-stone-400 leading-relaxed text-center px-3">
            {{ t('paywall_legal_blurb') }}
          </p>
          <div class="flex justify-center gap-3 text-[11px] font-zen flex-wrap">
            <RouterLink
              to="/terms"
              data-test="paywall-terms"
              class="text-stone-500 hover:text-peach-500 transition-colors underline-offset-2 hover:underline"
            >
              {{ t('paywall_link_terms') }}
            </RouterLink>
            <span class="text-stone-300">·</span>
            <RouterLink
              to="/privacy"
              data-test="paywall-privacy"
              class="text-stone-500 hover:text-peach-500 transition-colors underline-offset-2 hover:underline"
            >
              {{ t('paywall_link_privacy') }}
            </RouterLink>
            <span class="text-stone-300">·</span>
            <a
              href="https://support.apple.com/HT202039"
              target="_blank"
              rel="noopener"
              data-test="paywall-subscription-terms"
              class="text-stone-500 hover:text-peach-500 transition-colors underline-offset-2 hover:underline"
            >
              {{ t('paywall_link_manage_sub') }}
            </a>
          </div>
        </div>
      </template>
    </div>

    <div
      v-if="!initialLoad && !ent.isPremium()"
      class="fixed bottom-0 inset-x-0 z-30 bg-gradient-to-t from-cream-50 via-cream-50/95 to-transparent pt-6 pb-5 px-5 md:px-8"
    >
      <div class="max-w-md md:max-w-2xl lg:max-w-3xl mx-auto">
        <button
          type="button"
          data-test="subscribe-cta"
          :disabled="loading || !selectedId"
          class="w-full py-4 rounded-2xl bg-peach-gradient text-white font-display font-black text-base shadow-lg transition-all active:scale-[0.99] disabled:opacity-50"
          @click="ecpayCheckout"
        >
          {{ loading ? t('paywall_btn_subscribe_loading') : t('paywall_cta_subscribe_v2') }}
        </button>
      </div>
    </div>
  </div>
</template>
