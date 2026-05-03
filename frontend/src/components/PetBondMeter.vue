<script setup lang="ts">
/**
 * PetBondMeter — 寵物羈絆等級 + intimacy tier 標籤 + 進度 bar + 摸頭按鈕
 *
 * 6-tier intimacy progression:
 *   stranger → familiar → friendly → close → soulmate → legendary
 *
 * 點 pet 大圖（透過 #pet-area slot）→ petHead() → bond +1 動畫
 */
import { onMounted, onActivated } from 'vue'
import { usePetBond } from '../composables/usePetBond'
import { useTone } from '../composables/useTone'

defineProps<{
  showPetHeadButton?: boolean
}>()

const { t } = useTone()
const bond = usePetBond()

onMounted(() => bond.refresh())
onActivated(() => bond.refresh())

async function onPetHead() {
  await bond.petHead()
}
</script>

<template>
  <div class="rounded-3xl bg-gradient-to-br from-cream-50 to-peach-50 p-4 space-y-3" data-test="pet-bond-meter">
    <div v-if="!bond.state.value" class="text-center text-stone-400 font-zen text-xs py-4">
      {{ t('bond_meter_loading') }}
    </div>
    <template v-else>
      <div class="flex items-center justify-between">
        <div>
          <p class="font-zen text-[10px] uppercase tracking-widest text-stone-500">
            {{ t('bond_meter_label') }}
          </p>
          <p class="font-display font-bold text-peach-500 text-xl leading-tight mt-0.5">
            Lv {{ bond.state.value.bond_level }}
            <span class="font-zen text-xs text-peach-400 ml-1.5">{{ t(bond.intimacyKey.value) }}</span>
          </p>
        </div>
        <transition name="heart-pop">
          <span
            v-if="bond.showHeart.value"
            class="text-2xl"
            aria-hidden="true"
            data-test="bond-heart-pop"
          >
            💕 +{{ bond.animatingDelta.value }}
          </span>
        </transition>
      </div>

      <!-- progress bar -->
      <div>
        <div class="h-2 rounded-full bg-white/70 overflow-hidden">
          <div
            class="h-full bg-gradient-to-r from-peach-300 to-sakura-400 transition-[width] duration-700"
            :style="{ width: `${bond.state.value.progress_percent}%` }"
          />
        </div>
        <p class="font-zen text-[11px] text-stone-500 mt-1.5">
          {{ t('bond_progress_to_next', {
            xp: bond.state.value.bond_xp,
            need: bond.state.value.next_tier_at,
          }) }}
        </p>
      </div>

      <button
        v-if="showPetHeadButton"
        type="button"
        @click="onPetHead"
        class="w-full bg-white text-peach-500 font-zen text-xs font-bold py-2.5 rounded-2xl shadow-soft active:scale-95 transition-transform"
        data-test="pet-head-btn"
      >
        🤲 {{ t('bond_pet_head_btn') }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.heart-pop-enter-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.heart-pop-leave-active {
  transition: all 0.5s ease-out;
}
.heart-pop-enter-from {
  opacity: 0;
  transform: scale(0.4) translateY(6px);
}
.heart-pop-leave-to {
  opacity: 0;
  transform: scale(1.2) translateY(-12px);
}
@media (prefers-reduced-motion: reduce) {
  .heart-pop-enter-active,
  .heart-pop-leave-active {
    transition: opacity 0.2s;
  }
}
</style>
