/**
 * useOnboardingTour — Coachmark / spotlight onboarding for deep-RPG views
 *
 * Why: Wave 7+ shipped pet bonding / rank / skill paths / body dex / dodo stories /
 * 24 solar terms / random events / 33 outfits / 38 achievements ... but users said
 * "I don't know how to find these things". This composable orchestrates a non-blocking,
 * spotlight-only first-visit tour per view.
 *
 * Design:
 *   - One tour per view, identified by `key` (TOUR_KEYS).
 *   - Tour content lives in lib/tour-steps.ts (pure data, no logic).
 *   - localStorage tracks `seen` per key so repeat visits don't re-trigger.
 *   - sessionStorage debounces multi-view jumps in same session: only the FIRST
 *     view in a session auto-starts; later views queue silently (user can replay
 *     from Profile).
 *   - prefers-reduced-motion → spotlight animation off (instant cuts).
 *   - ESC key globally cancels current tour (when open).
 *
 * Module-singleton pattern (matches useI18n / useTone) so any component can mount
 * <CoachmarkOverlay/> once at App.vue level and read the same state.
 */
import { ref, computed, type Ref } from 'vue'
import type { TourKey, TourStep } from '../lib/tour-steps'
import { TOUR_STEPS } from '../lib/tour-steps'

const STORAGE_PREFIX = 'tour_seen_'
const SESSION_FLAG = 'tour_session_started'

// === module singletons ===
const active = ref<TourKey | null>(null)
const stepIndex = ref(0)

const currentSteps: Ref<TourStep[]> = computed(() =>
  active.value ? TOUR_STEPS[active.value] ?? [] : [],
)

const currentStep = computed<TourStep | null>(() =>
  currentSteps.value[stepIndex.value] ?? null,
)

const totalSteps = computed(() => currentSteps.value.length)

function safeLocalGet(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}
function safeLocalSet(key: string, val: string) {
  try {
    localStorage.setItem(key, val)
  } catch {
    /* private mode etc. */
  }
}
function safeLocalRemove(key: string) {
  try {
    localStorage.removeItem(key)
  } catch {
    /* swallow */
  }
}
function safeSessionGet(key: string): string | null {
  try {
    return sessionStorage.getItem(key)
  } catch {
    return null
  }
}
function safeSessionSet(key: string, val: string) {
  try {
    sessionStorage.setItem(key, val)
  } catch {
    /* swallow */
  }
}

function wasShown(key: TourKey): boolean {
  return safeLocalGet(STORAGE_PREFIX + key) === '1'
}

function markCompleted(key: TourKey) {
  safeLocalSet(STORAGE_PREFIX + key, '1')
}

function markSkipped(key: TourKey) {
  // Same effect as completed — won't auto-reshow. Profile "重看" link clears all.
  safeLocalSet(STORAGE_PREFIX + key, '1')
}

function start(key: TourKey) {
  if (!TOUR_STEPS[key] || TOUR_STEPS[key].length === 0) return
  active.value = key
  stepIndex.value = 0
  safeSessionSet(SESSION_FLAG, '1')
}

/**
 * Auto-start: only triggers if (a) never seen and (b) no tour already shown
 * this session. Lets users land on Calendar first without getting bombarded.
 * If user navigates straight to (e.g.) BodyDexView in their first session, that
 * tour fires — but later view changes within same session stay quiet.
 */
function startIfNew(key: TourKey) {
  if (wasShown(key)) return
  if (safeSessionGet(SESSION_FLAG) === '1' && !active.value) {
    // already showed one tour this session — defer
    return
  }
  // small delay so target elements have mounted (data-tour attrs on freshly-rendered DOM)
  setTimeout(() => {
    if (!wasShown(key) && !active.value) start(key)
  }, 400)
}

function next() {
  if (!active.value) return
  if (stepIndex.value < totalSteps.value - 1) {
    stepIndex.value += 1
  } else {
    // last step → finish
    markCompleted(active.value)
    close()
  }
}

function prev() {
  if (stepIndex.value > 0) stepIndex.value -= 1
}

function skip() {
  if (active.value) markSkipped(active.value)
  close()
}

function close() {
  active.value = null
  stepIndex.value = 0
}

/** Profile「重看朵朵教學」: clear all flags + restart at Calendar */
function resetAll() {
  for (const key of Object.keys(TOUR_STEPS)) {
    safeLocalRemove(STORAGE_PREFIX + key)
  }
  try {
    sessionStorage.removeItem(SESSION_FLAG)
  } catch {
    /* swallow */
  }
  close()
}

export function useOnboardingTour() {
  return {
    active,
    stepIndex,
    currentStep,
    currentSteps,
    totalSteps,
    start,
    startIfNew,
    next,
    prev,
    skip,
    close,
    wasShown,
    markCompleted,
    markSkipped,
    resetAll,
  }
}
