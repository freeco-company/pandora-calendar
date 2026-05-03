<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { FaqApi, type FaqGroup } from '../api'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useSfx } from '../lib/sound'
import { useTone } from '../composables/useTone'

const router = useRouter()
const sfx = useSfx()
const { t } = useTone()

const loading = ref(true)
const error = ref<string | null>(null)
const groups = ref<FaqGroup[]>([])
const openKey = ref<string | null>(null)

const CATEGORY_ICONS: Record<string, string> = {
  usage: '💡',
  privacy: '🔒',
  subscription: '💎',
  health: '🌸',
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await FaqApi.list()
    groups.value = res.data.data ?? []
  } catch {
    error.value = t('faq_load_failed')
  } finally {
    loading.value = false
  }
}

function toggle(key: string) {
  sfx.play('ui_tap')
  openKey.value = openKey.value === key ? null : key
}

function isInternal(href: string): boolean {
  return href.startsWith('/') && !href.startsWith('//')
}

onMounted(load)
</script>

<template>
  <div class="px-5 pt-10 pb-10 max-w-md mx-auto space-y-5">
    <header class="space-y-1">
      <button
        class="text-xs text-stone-500 font-zen mb-2"
        @click="router.back()"
      >
        {{ t('common_back') }}
      </button>
      <h1 class="font-display text-2xl font-bold text-peach-500">{{ t('faq_title') }}</h1>
      <p class="font-zen text-xs text-stone-500">
        {{ t('faq_subtitle') }}
      </p>
    </header>

    <Spinner v-if="loading" :label="t('faq_loading')" />

    <div v-else-if="error" class="space-y-3">
      <EmptyState icon="🌸" :title="error" />
      <Button full @click="load">{{ t('common_retry_short') }}</Button>
    </div>

    <EmptyState
      v-else-if="!groups.length"
      show-dodo
      :title="t('faq_empty_title')"
      :subtitle="t('faq_empty_subtitle')"
    />

    <div v-else class="space-y-4">
      <Card
        v-for="group in groups"
        :key="group.category"
        tone="plain"
        :padded="false"
        class="overflow-hidden"
      >
        <div class="px-5 py-3 border-b border-cream-100 flex items-center gap-2">
          <span class="text-lg">{{ CATEGORY_ICONS[group.category] ?? '🌷' }}</span>
          <h2 class="font-display font-bold text-peach-500 text-sm">{{ group.category_label }}</h2>
        </div>
        <div>
          <div
            v-for="(item, i) in group.items"
            :key="i"
            class="border-b border-cream-100 last:border-b-0"
          >
            <button
              class="w-full px-5 py-4 text-left flex items-center justify-between gap-3 hover:bg-peach-50 transition-colors"
              :aria-expanded="openKey === group.category + '-' + i"
              @click="toggle(group.category + '-' + i)"
            >
              <span class="font-zen text-sm text-stone-700">{{ item.q }}</span>
              <span
                class="text-stone-400 transition-transform shrink-0"
                :class="openKey === group.category + '-' + i ? 'rotate-180' : ''"
              >
                ⌄
              </span>
            </button>
            <div
              v-if="openKey === group.category + '-' + i"
              class="px-5 pb-4 space-y-2.5"
            >
              <p class="font-zen text-sm text-stone-600 leading-relaxed whitespace-pre-line">
                {{ item.a }}
              </p>
              <div
                v-if="item.related_links?.length"
                class="flex flex-wrap gap-2 pt-1"
              >
                <template v-for="(link, j) in item.related_links" :key="j">
                  <RouterLink
                    v-if="isInternal(link.href) && !link.external"
                    :to="link.href"
                    class="text-[11px] font-zen text-peach-500 bg-peach-50 px-3 py-1 rounded-full hover:bg-peach-100"
                  >
                    {{ link.label }} →
                  </RouterLink>
                  <a
                    v-else
                    :href="link.href"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-[11px] font-zen text-peach-500 bg-peach-50 px-3 py-1 rounded-full hover:bg-peach-100"
                  >
                    {{ link.label }} ↗
                  </a>
                </template>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <Card tone="cream" class="text-center space-y-3">
        <p class="font-display text-base font-bold text-peach-500">{{ t('faq_no_answer_title') }}</p>
        <p class="font-zen text-xs text-stone-500">
          {{ t('faq_no_answer_subtitle') }}
        </p>
        <Button full @click="router.push('/feedback')">{{ t('faq_no_answer_cta') }}</Button>
      </Card>
    </div>
  </div>
</template>
