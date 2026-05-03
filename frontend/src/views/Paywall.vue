<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { SubscriptionApi, type SubscriptionProduct } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()

const router = useRouter()
const ent = useEntitlementsStore()
const products = ref<SubscriptionProduct[]>([])
const features = ref<string[]>([])
const loading = ref(false)
const initialLoad = ref(true)
const restoring = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const selectedId = ref<string | null>(null)

onMounted(async () => {
  ent.load()
  try {
    const res = await SubscriptionApi.products()
    products.value = res.data.data
    features.value = res.data.features
    // default 預選年費（標 save 24%）
    selectedId.value = res.data.data.find((p) => p.id.endsWith('annual'))?.id
      ?? res.data.data[0]?.id
      ?? null
  } finally {
    initialLoad.value = false
  }
})

const annualProduct = computed(() => products.value.find((p) => p.id.endsWith('annual')))
const selectedProduct = computed(() => products.value.find((p) => p.id === selectedId.value))

/**
 * Premium 5 大賣點 — fallback 顯示（後端 features 沒設定或還沒回傳時）
 * 對應 backend FreemiumGate / Premium feature 列表（PMS / BBT 雙相 / 衛教文章 / 伴侶分享 / 無廣告）
 */
const PREMIUM_BENEFITS = computed<Array<{ emoji: string; title: string; desc: string }>>(() => [
  { emoji: '🌙', title: t('paywall_benefit_1_title'), desc: t('paywall_benefit_1_desc') },
  { emoji: '🌡', title: t('paywall_benefit_2_title'), desc: t('paywall_benefit_2_desc') },
  { emoji: '📰', title: t('paywall_benefit_3_title'), desc: t('paywall_benefit_3_desc') },
  { emoji: '💞', title: t('paywall_benefit_4_title'), desc: t('paywall_benefit_4_desc') },
  { emoji: '🚫', title: t('paywall_benefit_5_title'), desc: t('paywall_benefit_5_desc') },
])

const benefits = computed(() => {
  if (features.value.length >= 3) {
    // 後端有設定，優先用後端版本（仍保留 fallback emoji 風格）
    return features.value.map((f, idx) => ({
      emoji: ['🌙', '🌡', '📰', '💞', '🚫', '✨'][idx] ?? '✨',
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
    // Demo phase：show action url；prod 會是 form auto-post
    if (data?.data?.action_url) {
      message.value = t('paywall_msg_ecpay_ready')
      // TODO(prod): auto-submit form to ECPay action_url with form_params
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

/**
 * Restore Purchase — Apple / Google App Store 審核必要按鈕。
 * Web build 走 ent.load() 重新拉 entitlement；Capacitor build 之後接 IAP 套件的 restore API。
 *
 * TODO(P2 IAP): 在 Capacitor 環境呼叫
 *   - iOS: StoreKit 2 `Transaction.currentEntitlements` → 拿到 receipt → SubscriptionApi.verifyApple
 *   - Android: BillingClient `queryPurchasesAsync` → 拿到 purchaseToken → SubscriptionApi.verifyGoogle
 */
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
  <div class="px-5 md:px-8 pt-8 pb-10 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-5">
    <button
      @click="back"
      data-test="paywall-back"
      class="font-zen text-sm text-peach-500 hover:text-peach-400"
    >
      {{ t('paywall_btn_back') }}
    </button>

    <header class="text-center space-y-2">
      <div class="flex justify-center">
        <Character
          species="dodo"
          mood="cheering"
          outfit="fp_crown"
          :level="20"
          :size="130"
          :show-halo="true"
          :floaty="true"
        />
      </div>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('paywall_eyebrow_premium') }}</p>
      <h1 class="font-display text-3xl font-bold text-peach-500">{{ t('paywall_heading') }}</h1>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">
        {{ t('paywall_subtitle') }}
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
      <!-- Premium 5 賣點 -->
      <Card tone="cream" class="space-y-3" data-test="premium-benefits">
        <h3 class="font-display font-bold text-peach-500 text-base">{{ t('paywall_section_unlock') }}</h3>
        <ul class="space-y-3">
          <li
            v-for="(b, idx) in benefits"
            :key="idx"
            class="flex gap-3 items-start"
          >
            <span class="text-2xl shrink-0">{{ b.emoji }}</span>
            <div class="flex-1">
              <p class="font-zen text-sm font-semibold text-stone-700">{{ b.title }}</p>
              <p v-if="b.desc" class="font-zen text-[12px] text-stone-500 mt-0.5 leading-relaxed">
                {{ b.desc }}
              </p>
            </div>
          </li>
        </ul>
      </Card>

      <!-- Plan cards -->
      <div class="space-y-3" data-test="paywall-plans">
        <button
          v-for="p in products"
          :key="p.id"
          type="button"
          :data-test="`plan-${p.id}`"
          :disabled="loading"
          class="relative w-full bg-white rounded-3xl p-5 text-left transition-all active:scale-[0.99] disabled:opacity-50"
          :class="
            selectedId === p.id
              ? 'border-2 border-peach-400 ring-4 ring-peach-100 shadow-soft'
              : 'border border-cream-200 hover:border-peach-200'
          "
          @click="selectedId = p.id"
        >
          <span
            v-if="p === annualProduct"
            class="absolute -top-3 left-5 bg-gradient-to-r from-peach-400 to-sakura-400 text-white text-[10px] font-zen font-bold px-3 py-1 rounded-full shadow-soft"
          >
            {{ t('paywall_badge_popular') }}
          </span>
          <div class="flex justify-between items-baseline">
            <span class="font-display font-bold text-peach-500 text-base">{{ p.title }}</span>
            <span
              v-if="p.discount"
              class="text-[11px] bg-sakura-100 text-sakura-500 font-zen font-semibold px-2.5 py-0.5 rounded-full"
            >{{ p.discount }}</span>
          </div>
          <div class="mt-2 flex items-baseline gap-1">
            <span class="font-display font-black text-3xl text-peach-500">NT${{ p.price_twd }}</span>
            <span class="text-sm text-stone-500 font-normal font-zen">
              / {{ p.period === 'year' ? t('paywall_unit_year') : t('paywall_unit_month') }}
            </span>
          </div>
          <p
            v-if="p.monthly_equivalent"
            class="text-xs text-stone-500 mt-1 font-zen"
          >
            {{ t('paywall_monthly_equivalent', { amount: p.monthly_equivalent }) }}
          </p>
        </button>
      </div>

      <!-- Subscribe CTA -->
      <button
        type="button"
        data-test="subscribe-cta"
        :disabled="loading || !selectedId"
        class="w-full py-3.5 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft transition-all active:scale-[0.99] disabled:opacity-50"
        @click="ecpayCheckout"
      >
        {{ loading ? t('paywall_btn_subscribe_loading') : t('paywall_btn_subscribe') }}
      </button>

      <!-- Restore Purchase（App Store / Play 審核必要）-->
      <button
        type="button"
        data-test="restore-purchase"
        :disabled="restoring"
        class="w-full py-2.5 rounded-2xl bg-white border border-cream-200 text-stone-500 font-zen text-sm transition-all active:scale-[0.99] hover:bg-cream-50 disabled:opacity-50"
        @click="restorePurchase"
      >
        {{ restoring ? t('paywall_btn_restore_loading') : t('paywall_btn_restore') }}
      </button>

      <p v-if="message" class="text-center text-peach-500 text-sm font-zen">{{ message }}</p>
      <p v-if="error" class="text-center text-sakura-500 text-sm font-zen">{{ error }}</p>

      <!-- Social proof（占位，prod 接後端 stats） -->
      <!-- TODO(backend): /api/v1/subscription/social-proof 回傳 { active_users: number }；
           暫以 5,000+ 占位，prod 上線前要確認真實數字才能露出，避免不實宣傳 -->
      <Card tone="plain" class="text-center space-y-1">
        <div class="flex justify-center gap-1 text-peach-400">
          <span v-for="i in 5" :key="i">★</span>
        </div>
        <p class="font-zen text-[12px] text-stone-500">
          {{ t('paywall_social_proof') }}
        </p>
      </Card>

      <!-- 訂閱條款 / 法律連結（App Store / Play 審核必要） -->
      <div class="text-center space-y-2 pt-1">
        <p class="font-zen text-[10px] text-stone-400 leading-relaxed">
          {{ t('paywall_legal_blurb') }}
        </p>
        <div class="flex justify-center gap-3 text-[11px] font-zen">
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

    <p class="text-center text-[10px] text-stone-400 pt-3 font-zen leading-relaxed">
      {{ t('paywall_footer_no_ads') }}
    </p>
  </div>
</template>
