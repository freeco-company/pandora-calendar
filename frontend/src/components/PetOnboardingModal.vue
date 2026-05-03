<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { PetApi } from '../api'
import Character from './Character.vue'
import Button from './ui/Button.vue'
import { useSfx } from '../lib/sound'

const sfx = useSfx()
const open = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)
const species = ref<string>('cat')
const nickname = ref('')
const available = ref<string[]>([])

const SPECIES_LABEL: Record<string, string> = {
  cat: '貓貓',
  rabbit: '兔兔',
  dog: '狗狗',
  fox: '狐狸',
  bear: '熊熊',
  penguin: '企鵝',
  pig: '豬豬',
  sheep: '羊羊',
  dinosaur: '小恐龍',
  tiger: '老虎',
  robot: '機器人',
}

onMounted(async () => {
  try {
    const { data } = await PetApi.show()
    available.value = data.data.available_species
    if (!data.data.onboarded) {
      open.value = true
      // 預設選第一隻
      species.value = available.value[0] ?? 'cat'
    }
  } catch {
    /* 未登入或網路問題：不彈 modal，由路由 guard 處理 */
  }
})

function pickSpecies(s: string) {
  sfx.play('choice_select')
  species.value = s
}

async function confirm() {
  if (!nickname.value.trim()) {
    error.value = '請給寵物取個暱稱'
    return
  }
  loading.value = true
  error.value = null
  try {
    await PetApi.update(species.value, nickname.value.trim())
    sfx.play('correct')
    open.value = false
    // reload page so all components pick up new pet
    location.reload()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '存檔失敗'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Transition name="ach">
    <div
      v-if="open"
      class="fixed inset-0 z-[80] bg-stone-900/50 backdrop-blur-sm flex items-end sm:items-center justify-center p-4"
      data-test="pet-onboarding-modal"
    >
      <div class="w-full max-w-sm bg-cream-50 rounded-3xl p-6 shadow-soft-lg space-y-4 animate-pop">
        <header class="text-center space-y-1">
          <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Welcome</p>
          <h2 class="font-display text-xl font-bold text-peach-500">挑一個夥伴陪妳</h2>
          <p class="font-zen text-[12px] text-stone-500">妳每記錄一次週期，朵朵就會幫忙照顧妳的小夥伴。</p>
        </header>

        <!-- Selected pet preview -->
        <div class="flex justify-center bg-white/70 rounded-3xl py-4">
          <Character
            :species="(species as any)"
            :size="120"
            mood="happy"
            :show-halo="true"
            :floaty="true"
          />
        </div>

        <!-- Species grid -->
        <div class="grid grid-cols-4 gap-2">
          <button
            v-for="s in available"
            :key="s"
            @click="pickSpecies(s)"
            :data-test="`pet-species-${s}`"
            class="py-2 rounded-2xl border text-[11px] font-zen transition-all active:scale-95"
            :class="
              species === s
                ? 'bg-peach-gradient text-white border-transparent shadow-soft'
                : 'bg-white border-cream-200 text-stone-600 hover:bg-peach-50'
            "
          >
            {{ SPECIES_LABEL[s] || s }}
          </button>
        </div>

        <!-- Nickname -->
        <label class="block">
          <span class="font-zen text-[11px] text-stone-500">幫牠取個名字</span>
          <input
            v-model="nickname"
            maxlength="32"
            placeholder="例如：小灰、糰子、貝貝"
            data-test="pet-nickname-input"
            class="mt-1 w-full px-4 py-2.5 rounded-2xl border border-cream-200 bg-white focus:outline-none focus:border-peach-300 text-sm font-zen"
          />
        </label>

        <p v-if="error" class="text-xs text-sakura-500 text-center font-zen">{{ error }}</p>

        <Button
          full
          variant="primary"
          :loading="loading"
          @click="confirm"
          data-test="pet-onboarding-confirm"
        >
          就決定是你了
        </Button>

        <p class="text-[10px] text-stone-400 text-center font-zen">
          隨時能在「我的」改名字 / 換 outfit
        </p>
      </div>
    </div>
  </Transition>
</template>
