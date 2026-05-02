<script setup lang="ts">
import { computed } from 'vue'
import {
  ANIMAL_META,
  accessoryPath,
  anchorPath,
  moodBadgePath,
  outfitStyle,
  rarityOf,
  type Mood,
  type Outfit,
  type Species,
} from '../lib/character'
import { useSfx } from '../lib/sound'

const props = withDefaults(
  defineProps<{
    species?: Species
    level?: number
    mood?: Mood
    outfit?: Outfit
    size?: number // px
    showRarity?: boolean
    showHalo?: boolean
    floaty?: boolean
    interactive?: boolean
    label?: string | null
  }>(),
  {
    species: 'rabbit',
    level: 1,
    mood: 'happy',
    outfit: 'none',
    size: 160,
    showRarity: false,
    showHalo: true,
    floaty: true,
    interactive: false,
    label: null,
  },
)

const sfx = useSfx()

const meta = computed(() => ANIMAL_META[props.species] || ANIMAL_META.rabbit)
const anchor = computed(() => anchorPath(props.species))
const moodBadge = computed(() => moodBadgePath(props.mood))
const accessory = computed(() => accessoryPath(props.level))
const rarity = computed(() => rarityOf(props.level))
const outfit = computed(() => outfitStyle(props.outfit, props.species))

const stageStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
}))

function pet() {
  if (!props.interactive) return
  sfx.play('pet')
}
</script>

<template>
  <div class="inline-flex flex-col items-center gap-2">
    <div
      class="char-stage relative"
      :style="stageStyle"
      :class="{ 'cursor-pointer': interactive, 'animate-floaty': floaty }"
      @click="pet"
    >
      <div
        v-if="showHalo"
        class="absolute inset-0 rounded-full blur-2xl opacity-60 -z-10"
        :style="{ background: meta.halo }"
      />
      <img class="char-body" :src="anchor" :alt="meta.name" draggable="false" />

      <img
        v-if="outfit"
        :class="outfit.className"
        :src="outfit.src"
        alt=""
        :style="outfit.style"
        draggable="false"
      />

      <img v-if="accessory" class="char-accessory" :src="accessory" alt="" draggable="false" />
      <img v-if="moodBadge" class="char-mood-badge animate-sparkle" :src="moodBadge" alt="" draggable="false" />
    </div>

    <div
      v-if="showRarity"
      class="px-3 py-1 rounded-full text-[11px] font-zen font-semibold"
      :style="{
        background: rarity.gradient,
        color: rarity.textColor,
        textShadow: rarity.shadow,
      }"
    >
      {{ rarity.name }} · LV.{{ level }}
    </div>

    <p v-if="label" class="text-xs text-stone-500 font-zen">{{ label }}</p>
  </div>
</template>
