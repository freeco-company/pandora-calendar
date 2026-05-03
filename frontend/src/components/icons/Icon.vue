<script setup lang="ts">
/**
 * Icon — group SVG wrapper component (inline fallback for pandora-calendar)
 *
 * Per group spec [docs/group-naming-and-voice + project_design_svg_package_adr010]:
 * - Style: badge v5 — soft gradient, rounded, 療癒可愛
 * - Animations: ease-in-out, breathing / flicker / heartbeat / sway
 * - Inline SVG only (no extra HTTP), every icon shipped via this single file
 * - A11y: role="img" + aria-label, decorative -> aria-hidden
 *
 * NOTE: When @freeco-company/pandora-design-svg becomes installable
 * (GITHUB_TOKEN setup), swap individual <component> branches to package imports
 * — the <Icon /> public API stays stable.
 */
import { computed } from 'vue'

export type IconName =
  // Cycle phases
  | 'phase-menstrual' // drop
  | 'phase-follicular' // leaf
  | 'phase-ovulation' // sun-sparkle
  | 'phase-luteal' // moon
  // Streak / gamification
  | 'flame'
  | 'sprout'
  | 'sparkle'
  | 'gem'
  | 'star'
  | 'trophy'
  // Difficulty traffic light
  | 'dot-easy'
  | 'dot-medium'
  | 'dot-hard'
  // Feedback emotion
  | 'heart'
  | 'face-neutral'
  | 'rain-cloud'
  // Misc
  | 'clock'
  | 'check'
  | 'dodo' // 朵朵 chick anchor
  | 'journal'
  | 'flower-sakura'

const props = withDefaults(
  defineProps<{
    name: IconName
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | number
    /** Optional Tailwind text-* class — SVG uses currentColor */
    colorClass?: string
    /** Enable looping breathing/flicker/etc animation */
    animated?: boolean
    /** A11y: visible label or 'decorative' */
    label?: string
    decorative?: boolean
  }>(),
  {
    size: 'md',
    animated: false,
    decorative: false,
  },
)

const px = computed(() => {
  if (typeof props.size === 'number') return props.size
  return { xs: 12, sm: 16, md: 20, lg: 28, xl: 40 }[props.size]
})

const animClass = computed(() => {
  if (!props.animated) return ''
  switch (props.name) {
    case 'flame':
      return 'icon-anim-flicker'
    case 'sparkle':
      return 'icon-anim-sparkle'
    case 'heart':
      return 'icon-anim-heartbeat'
    case 'phase-luteal':
      return 'icon-anim-breathe'
    case 'phase-menstrual':
      return 'icon-anim-bob'
    case 'phase-follicular':
    case 'sprout':
      return 'icon-anim-sway'
    case 'phase-ovulation':
    case 'star':
    case 'gem':
      return 'icon-anim-sparkle'
    case 'rain-cloud':
      return 'icon-anim-bob'
    case 'dodo':
      return 'icon-anim-floaty'
    default:
      return ''
  }
})

const a11y = computed(() =>
  props.decorative
    ? { 'aria-hidden': 'true' as const }
    : { role: 'img' as const, 'aria-label': props.label ?? props.name },
)
</script>

<template>
  <svg
    :width="px"
    :height="px"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    :class="[colorClass, animClass, 'inline-block align-middle']"
    v-bind="a11y"
  >
    <title v-if="!decorative">{{ label ?? name }}</title>

    <!-- Phase: menstrual (drop) -->
    <g v-if="name === 'phase-menstrual'">
      <defs>
        <linearGradient :id="`g-drop-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#fca5a5" />
          <stop offset="100%" stop-color="#f87171" />
        </linearGradient>
      </defs>
      <path
        d="M12 3 C 7.5 9, 5.5 12.5, 5.5 15.5 a 6.5 6.5 0 0 0 13 0 C 18.5 12.5, 16.5 9, 12 3 Z"
        :fill="`url(#g-drop-${name})`"
        stroke="currentColor"
        stroke-opacity="0.15"
        stroke-width="0.6"
      />
      <ellipse cx="9.5" cy="13" rx="1.4" ry="2.2" fill="white" fill-opacity="0.45" />
    </g>

    <!-- Phase: follicular (leaf) -->
    <g v-else-if="name === 'phase-follicular'">
      <defs>
        <linearGradient :id="`g-leaf-${name}`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#a7f3d0" />
          <stop offset="100%" stop-color="#34d399" />
        </linearGradient>
      </defs>
      <path
        d="M4 18 C 4 9, 11 4, 20 4 C 20 13, 14 20, 5 20 C 5 19, 4.5 18.5, 4 18 Z"
        :fill="`url(#g-leaf-${name})`"
      />
      <path d="M5 19 C 9 14, 13 10, 18 7" stroke="white" stroke-opacity="0.6" stroke-width="0.9" stroke-linecap="round" fill="none" />
    </g>

    <!-- Phase: ovulation (sparkle-sun) -->
    <g v-else-if="name === 'phase-ovulation'">
      <defs>
        <radialGradient :id="`g-ov-${name}`" cx="0.5" cy="0.5" r="0.5">
          <stop offset="0%" stop-color="#fde68a" />
          <stop offset="100%" stop-color="#fbbf24" />
        </radialGradient>
      </defs>
      <circle cx="12" cy="12" r="4.6" :fill="`url(#g-ov-${name})`" />
      <g stroke="#fbbf24" stroke-width="1.4" stroke-linecap="round" opacity="0.85">
        <path d="M12 2.5 L12 5" />
        <path d="M12 19 L12 21.5" />
        <path d="M2.5 12 L5 12" />
        <path d="M19 12 L21.5 12" />
        <path d="M5.2 5.2 L7 7" />
        <path d="M17 17 L18.8 18.8" />
        <path d="M5.2 18.8 L7 17" />
        <path d="M17 7 L18.8 5.2" />
      </g>
    </g>

    <!-- Phase: luteal (moon) -->
    <g v-else-if="name === 'phase-luteal'">
      <defs>
        <linearGradient :id="`g-moon-${name}`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#c4b5fd" />
          <stop offset="100%" stop-color="#8b5cf6" />
        </linearGradient>
      </defs>
      <path
        d="M16.5 4 A 9 9 0 1 0 20 15.5 A 7 7 0 0 1 16.5 4 Z"
        :fill="`url(#g-moon-${name})`"
      />
      <circle cx="9" cy="10" r="0.7" fill="white" fill-opacity="0.55" />
      <circle cx="11" cy="14" r="0.5" fill="white" fill-opacity="0.4" />
    </g>

    <!-- Flame -->
    <g v-else-if="name === 'flame'">
      <defs>
        <linearGradient :id="`g-flame-${name}`" x1="0" y1="1" x2="0" y2="0">
          <stop offset="0%" stop-color="#fb923c" />
          <stop offset="60%" stop-color="#f97316" />
          <stop offset="100%" stop-color="#fbbf24" />
        </linearGradient>
      </defs>
      <path
        d="M12 2.5 C 13.5 6, 17 7.5, 17 12.5 C 17 16.5, 14.7 19.5, 12 19.5 C 9.3 19.5, 7 16.5, 7 12.5 C 7 9.5, 9 8, 9.8 5.5 C 10.4 7, 11.2 7.5, 12 6.5 C 11.5 5.2, 11.5 4, 12 2.5 Z"
        :fill="`url(#g-flame-${name})`"
      />
      <path
        d="M12 11.5 C 13 13, 14 14, 14 16 C 14 17.5, 13 18.5, 12 18.5 C 11 18.5, 10 17.5, 10 16 C 10 14.5, 11 13.5, 12 11.5 Z"
        fill="#fef3c7"
        fill-opacity="0.85"
      />
    </g>

    <!-- Sprout -->
    <g v-else-if="name === 'sprout'">
      <path d="M12 21 L12 12" stroke="#65a30d" stroke-width="1.6" stroke-linecap="round" />
      <path
        d="M12 12 C 12 9, 9.5 7, 6 7 C 6.5 10.5, 9 12.5, 12 12.5"
        fill="#86efac"
        stroke="#65a30d"
        stroke-width="0.6"
      />
      <path
        d="M12 12 C 12 9.5, 14.5 7.5, 18 7.5 C 17.5 11, 15 12.5, 12 12.5"
        fill="#bbf7d0"
        stroke="#65a30d"
        stroke-width="0.6"
      />
    </g>

    <!-- Sparkle (4-point star with glow) -->
    <g v-else-if="name === 'sparkle'">
      <defs>
        <radialGradient :id="`g-spk-${name}`">
          <stop offset="0%" stop-color="#fef3c7" />
          <stop offset="100%" stop-color="#fbbf24" />
        </radialGradient>
      </defs>
      <path
        d="M12 2 L13.5 10.5 L22 12 L13.5 13.5 L12 22 L10.5 13.5 L2 12 L10.5 10.5 Z"
        :fill="`url(#g-spk-${name})`"
      />
      <circle cx="12" cy="12" r="1.6" fill="white" fill-opacity="0.9" />
    </g>

    <!-- Gem -->
    <g v-else-if="name === 'gem'">
      <defs>
        <linearGradient :id="`g-gem-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#a5f3fc" />
          <stop offset="100%" stop-color="#06b6d4" />
        </linearGradient>
      </defs>
      <path
        d="M6 9 L9 4 L15 4 L18 9 L12 21 Z"
        :fill="`url(#g-gem-${name})`"
        stroke="#0891b2"
        stroke-width="0.7"
        stroke-linejoin="round"
      />
      <path d="M6 9 L18 9" stroke="white" stroke-opacity="0.6" stroke-width="0.6" />
      <path d="M9 4 L12 9 L15 4" stroke="white" stroke-opacity="0.6" stroke-width="0.6" fill="none" />
      <path d="M12 9 L12 21" stroke="white" stroke-opacity="0.5" stroke-width="0.5" />
    </g>

    <!-- Star -->
    <g v-else-if="name === 'star'">
      <defs>
        <linearGradient :id="`g-star-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#fde68a" />
          <stop offset="100%" stop-color="#f59e0b" />
        </linearGradient>
      </defs>
      <path
        d="M12 2.5 L14.6 9 L21.5 9.5 L16.2 13.8 L18 20.5 L12 16.8 L6 20.5 L7.8 13.8 L2.5 9.5 L9.4 9 Z"
        :fill="`url(#g-star-${name})`"
        stroke="#d97706"
        stroke-width="0.6"
        stroke-linejoin="round"
      />
    </g>

    <!-- Trophy -->
    <g v-else-if="name === 'trophy'">
      <defs>
        <linearGradient :id="`g-tr-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#fde68a" />
          <stop offset="100%" stop-color="#d97706" />
        </linearGradient>
      </defs>
      <path d="M7 3 L17 3 L17 9 A 5 5 0 0 1 7 9 Z" :fill="`url(#g-tr-${name})`" />
      <path d="M5 5 L7 5 L7 9 A 2.5 2.5 0 0 1 4.5 6.5 Z" fill="#fbbf24" />
      <path d="M19 5 L17 5 L17 9 A 2.5 2.5 0 0 0 19.5 6.5 Z" fill="#fbbf24" />
      <rect x="10" y="14" width="4" height="3" fill="#d97706" />
      <rect x="7.5" y="17" width="9" height="2" rx="0.5" fill="#d97706" />
    </g>

    <!-- Difficulty dots -->
    <g v-else-if="name === 'dot-easy'">
      <circle cx="12" cy="12" r="6" fill="#86efac" stroke="#16a34a" stroke-width="0.8" />
      <circle cx="10" cy="10" r="1.5" fill="white" fill-opacity="0.7" />
    </g>
    <g v-else-if="name === 'dot-medium'">
      <circle cx="12" cy="12" r="6" fill="#fcd34d" stroke="#d97706" stroke-width="0.8" />
      <circle cx="10" cy="10" r="1.5" fill="white" fill-opacity="0.7" />
    </g>
    <g v-else-if="name === 'dot-hard'">
      <circle cx="12" cy="12" r="6" fill="#fca5a5" stroke="#dc2626" stroke-width="0.8" />
      <circle cx="10" cy="10" r="1.5" fill="white" fill-opacity="0.7" />
    </g>

    <!-- Heart -->
    <g v-else-if="name === 'heart'">
      <defs>
        <linearGradient :id="`g-hrt-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#fbcfe8" />
          <stop offset="100%" stop-color="#ec4899" />
        </linearGradient>
      </defs>
      <path
        d="M12 20 C 4 14, 3 9, 6.5 6.5 C 9 4.7, 11 5.5, 12 7.5 C 13 5.5, 15 4.7, 17.5 6.5 C 21 9, 20 14, 12 20 Z"
        :fill="`url(#g-hrt-${name})`"
      />
      <ellipse cx="9" cy="9.5" rx="1.4" ry="1" fill="white" fill-opacity="0.55" transform="rotate(-25 9 9.5)" />
    </g>

    <!-- Neutral face -->
    <g v-else-if="name === 'face-neutral'">
      <circle cx="12" cy="12" r="9" fill="#fde68a" stroke="#d97706" stroke-width="0.7" />
      <circle cx="9" cy="10" r="1" fill="#78350f" />
      <circle cx="15" cy="10" r="1" fill="#78350f" />
      <path d="M8.5 15 L15.5 15" stroke="#78350f" stroke-width="1.2" stroke-linecap="round" />
    </g>

    <!-- Rain cloud -->
    <g v-else-if="name === 'rain-cloud'">
      <defs>
        <linearGradient :id="`g-cl-${name}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#e0e7ff" />
          <stop offset="100%" stop-color="#94a3b8" />
        </linearGradient>
      </defs>
      <path
        d="M6 13 A 3.5 3.5 0 0 1 8.5 7.2 A 4.5 4.5 0 0 1 17 8 A 3.5 3.5 0 0 1 17.5 14.5 L7 14.5 A 3 3 0 0 1 6 13 Z"
        :fill="`url(#g-cl-${name})`"
      />
      <g fill="#60a5fa">
        <path d="M9 17 L8 20" stroke="#60a5fa" stroke-width="1.4" stroke-linecap="round" />
        <path d="M12.5 17 L11.5 20.5" stroke="#60a5fa" stroke-width="1.4" stroke-linecap="round" />
        <path d="M16 17 L15 20" stroke="#60a5fa" stroke-width="1.4" stroke-linecap="round" />
      </g>
    </g>

    <!-- Clock -->
    <g v-else-if="name === 'clock'">
      <circle cx="12" cy="12" r="9" fill="#fef3c7" stroke="#d97706" stroke-width="1" />
      <path d="M12 7 L12 12 L15.5 14" stroke="#d97706" stroke-width="1.6" stroke-linecap="round" fill="none" />
    </g>

    <!-- Check -->
    <g v-else-if="name === 'check'">
      <circle cx="12" cy="12" r="9" fill="#bbf7d0" />
      <path d="M7.5 12 L11 15.5 L17 9" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </g>

    <!-- Dodo (chick) — 朵朵 anchor. Simplified vector chibi (per group SVG style guide v1) -->
    <g v-else-if="name === 'dodo'">
      <defs>
        <radialGradient :id="`g-dodo-${name}`" cx="0.5" cy="0.4" r="0.7">
          <stop offset="0%" stop-color="#fff7d6" />
          <stop offset="100%" stop-color="#fcd34d" />
        </radialGradient>
      </defs>
      <ellipse cx="12" cy="14" rx="7.5" ry="7.2" :fill="`url(#g-dodo-${name})`" />
      <ellipse cx="12" cy="8.5" rx="5.8" ry="5.2" :fill="`url(#g-dodo-${name})`" />
      <circle cx="9.6" cy="8" r="0.95" fill="#1f2937" />
      <circle cx="14.4" cy="8" r="0.95" fill="#1f2937" />
      <circle cx="9.9" cy="7.6" r="0.3" fill="white" />
      <circle cx="14.7" cy="7.6" r="0.3" fill="white" />
      <path d="M11 10 L13 10 L12 11.4 Z" fill="#f97316" />
      <ellipse cx="9" cy="10.5" rx="1.1" ry="0.7" fill="#fca5a5" fill-opacity="0.6" />
      <ellipse cx="15" cy="10.5" rx="1.1" ry="0.7" fill="#fca5a5" fill-opacity="0.6" />
    </g>

    <!-- Journal -->
    <g v-else-if="name === 'journal'">
      <rect x="4.5" y="3.5" width="14" height="17" rx="1.5" fill="#fde68a" stroke="#d97706" stroke-width="0.8" />
      <rect x="4.5" y="3.5" width="3" height="17" fill="#f59e0b" />
      <path d="M9.5 8 L16 8 M9.5 11 L16 11 M9.5 14 L14 14" stroke="#92400e" stroke-width="0.9" stroke-linecap="round" />
    </g>

    <!-- Flower (sakura) -->
    <g v-else-if="name === 'flower-sakura'">
      <g fill="#fbcfe8" stroke="#ec4899" stroke-width="0.5">
        <ellipse cx="12" cy="6" rx="2.2" ry="3.2" />
        <ellipse cx="17" cy="10" rx="3.2" ry="2.2" />
        <ellipse cx="15" cy="16" rx="2.6" ry="3" transform="rotate(35 15 16)" />
        <ellipse cx="9" cy="16" rx="2.6" ry="3" transform="rotate(-35 9 16)" />
        <ellipse cx="7" cy="10" rx="3.2" ry="2.2" />
      </g>
      <circle cx="12" cy="11.5" r="1.6" fill="#fbbf24" />
    </g>
  </svg>
</template>

<style scoped>
/* Group SVG style guide v1: ease-in-out, soft loops, 療癒可愛 */
.icon-anim-flicker {
  animation: icon-flicker 1.6s ease-in-out infinite;
  transform-origin: 50% 80%;
}
@keyframes icon-flicker {
  0%, 100% { transform: scale(1) rotate(0deg); }
  25% { transform: scale(1.04) rotate(-1.5deg); }
  50% { transform: scale(0.97) rotate(0.8deg); }
  75% { transform: scale(1.02) rotate(1.2deg); }
}

.icon-anim-sparkle {
  animation: icon-sparkle 2.2s ease-in-out infinite;
  transform-origin: center;
}
@keyframes icon-sparkle {
  0%, 100% { transform: scale(1) rotate(0deg); opacity: 1; }
  50% { transform: scale(1.12) rotate(8deg); opacity: 0.92; }
}

.icon-anim-heartbeat {
  animation: icon-heartbeat 1.4s ease-in-out infinite;
  transform-origin: center;
}
@keyframes icon-heartbeat {
  0%, 60%, 100% { transform: scale(1); }
  20% { transform: scale(1.12); }
  40% { transform: scale(1.04); }
}

.icon-anim-breathe {
  animation: icon-breathe 4s ease-in-out infinite;
  transform-origin: center;
}
@keyframes icon-breathe {
  0%, 100% { transform: scale(0.96); }
  50% { transform: scale(1.05); }
}

.icon-anim-bob {
  animation: icon-bob 2.4s ease-in-out infinite;
}
@keyframes icon-bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-2px); }
}

.icon-anim-sway {
  animation: icon-sway 3.2s ease-in-out infinite;
  transform-origin: 50% 90%;
}
@keyframes icon-sway {
  0%, 100% { transform: rotate(-3deg); }
  50% { transform: rotate(3deg); }
}

.icon-anim-floaty {
  animation: icon-floaty 3s ease-in-out infinite;
}
@keyframes icon-floaty {
  0%, 100% { transform: translateY(0) rotate(-1deg); }
  50% { transform: translateY(-3px) rotate(1deg); }
}

@media (prefers-reduced-motion: reduce) {
  .icon-anim-flicker,
  .icon-anim-sparkle,
  .icon-anim-heartbeat,
  .icon-anim-breathe,
  .icon-anim-bob,
  .icon-anim-sway,
  .icon-anim-floaty {
    animation: none;
  }
}
</style>
