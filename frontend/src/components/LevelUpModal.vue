<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useSfx } from '../lib/sound'
import type { LevelUpDetail } from '../lib/gamification'
import Character from './Character.vue'
import Icon from './icons/Icon.vue'
import Button from './ui/Button.vue'

const sfx = useSfx()
const detail = ref<LevelUpDetail | null>(null)

function onLevelUp(ev: Event) {
  const ce = ev as CustomEvent<LevelUpDetail>
  if (!ce.detail) return
  detail.value = ce.detail
  sfx.play('level_up')
}

function close() {
  sfx.play('ui_close')
  detail.value = null
}

onMounted(() => window.addEventListener('pandora:level_up', onLevelUp))
onUnmounted(() => window.removeEventListener('pandora:level_up', onLevelUp))
</script>

<template>
  <Transition name="modal">
    <div
      v-if="detail"
      class="fixed inset-0 z-[70] flex items-center justify-center px-6"
      data-test="level-up-modal"
      @click.self="close"
    >
      <div class="absolute inset-0 bg-gradient-to-br from-peach-100/90 to-lavender-100/90 backdrop-blur-md animate-fadein" />
      <div class="relative w-full max-w-sm surface-card p-7 text-center animate-pop space-y-4">
        <p class="font-zen text-xs text-stone-500 tracking-widest inline-flex items-center justify-center gap-1.5">LEVEL UP <Icon name="sparkle" size="sm" animated decorative class="text-peach-500" /></p>
        <h2 class="font-display text-3xl font-bold text-peach-500">LV.{{ detail.level }} に昇進！</h2>

        <div class="flex justify-center my-3">
          <Character :level="detail.level" mood="cheering" species="dodo" :size="120" :show-rarity="true" :floaty="false" />
        </div>

        <p v-if="detail.cheer" class="text-sm text-stone-600 leading-relaxed font-zen">
          {{ detail.cheer }}
        </p>
        <p v-if="detail.outfit_unlocked" class="text-xs text-peach-500 font-zen">
          🎀 解鎖新裝扮：{{ detail.outfit_unlocked }}
        </p>

        <Button variant="primary" full @click="close">繼續</Button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
