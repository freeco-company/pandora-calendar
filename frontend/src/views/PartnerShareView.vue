<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PartnerApi, type PartnerShareState } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()
const state = ref<PartnerShareState | null>(null)
const loading = ref(true)
const copied = ref(false)

async function load() {
  loading.value = true
  try {
    state.value = (await PartnerApi.show()).data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function enable() {
  if (!confirm(t('partner_enable_confirm'))) return
  await PartnerApi.enable()
  await load()
}

async function disable() {
  if (!confirm(t('partner_disable_confirm'))) return
  await PartnerApi.disable()
  await load()
}

async function copyUrl() {
  if (!state.value?.share_url) return
  try {
    await navigator.clipboard.writeText(state.value.share_url)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {/* clipboard 不可用就不處理 */}
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">{{ t('common_back') }}</button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('partner_eyebrow') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ t('partner_title') }}</h1>
      <p class="font-zen text-[12px] text-stone-500 mt-1">{{ t('partner_blurb') }}</p>
    </header>

    <Card tone="cream" class="text-sm font-zen leading-relaxed text-stone-700 space-y-2">
      <p class="font-bold text-peach-500">{{ t('partner_visible_title') }}</p>
      <ul class="list-disc list-inside text-stone-600 text-[13px]">
        <li>{{ t('partner_visible_phase') }}</li>
        <li>{{ t('partner_visible_eta') }}</li>
        <li>{{ t('partner_visible_hint') }}</li>
      </ul>
      <p class="font-bold text-peach-500 mt-2">{{ t('partner_hidden_title') }}</p>
      <ul class="list-disc list-inside text-stone-600 text-[13px]">
        <li>{{ t('partner_hidden_records') }}</li>
        <li>{{ t('partner_hidden_symptoms') }}</li>
        <li>{{ t('partner_hidden_checkin') }}</li>
        <li>{{ t('partner_hidden_pii') }}</li>
      </ul>
    </Card>

    <template v-if="loading">
      <p class="text-center text-stone-400 font-zen text-sm">{{ t('partner_loading') }}</p>
    </template>

    <template v-else-if="state?.enabled && state.share_url">
      <Card tone="plain" class="space-y-3">
        <p class="font-zen text-[11px] text-stone-500">{{ t('partner_share_url_label') }}</p>
        <div class="bg-cream-50 rounded-2xl p-3 break-all font-mono text-[11px] text-stone-700">
          {{ state.share_url }}
        </div>
        <div class="flex gap-2">
          <Button full variant="primary" @click="copyUrl">{{ copied ? t('partner_copied_btn') : t('partner_copy_btn') }}</Button>
          <Button variant="secondary" @click="disable">{{ t('partner_disable_btn') }}</Button>
        </div>
        <p class="text-[10px] text-stone-400 font-zen">
          {{ t('partner_enabled_at', { date: state.enabled_at?.slice(0, 10) ?? '' }) }}
        </p>
      </Card>
    </template>

    <template v-else>
      <Card tone="plain">
        <Button full variant="primary" @click="enable">{{ t('partner_enable_btn') }}</Button>
      </Card>
    </template>
  </div>
</template>
