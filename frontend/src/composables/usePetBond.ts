/**
 * usePetBond — 寵物羈絆狀態 + 摸頭 / 餵食動作
 */
import { ref, computed } from 'vue'
import { PetBondApi, type PetBondState, type IntimacyTier } from '../api'

const state = ref<PetBondState | null>(null)
const loading = ref(false)
const animatingDelta = ref<number>(0)
const showHeart = ref(false)

const TIER_LABEL: Record<IntimacyTier, string> = {
  stranger: 'bond_tier_stranger',
  familiar: 'bond_tier_familiar',
  friendly: 'bond_tier_friendly',
  close: 'bond_tier_close',
  soulmate: 'bond_tier_soulmate',
  legendary: 'bond_tier_legendary',
}

async function refresh() {
  loading.value = true
  try {
    const r = await PetBondApi.show()
    state.value = r.data?.data ?? null
  } catch {
    state.value = null
  } finally {
    loading.value = false
  }
}

async function petHead() {
  try {
    const r = await PetBondApi.petHead()
    if (r.data?.data) {
      const next = r.data.data
      animatingDelta.value = next.delta ?? 0
      showHeart.value = true
      setTimeout(() => (showHeart.value = false), 1200)
      state.value = {
        species: next.species,
        bond_xp: next.bond_xp,
        bond_level: next.bond_level,
        intimacy_tier: next.intimacy_tier,
        next_tier_at: next.next_tier_at,
        progress_percent: next.progress_percent,
      }
    }
  } catch {
    // graceful
  }
}

async function feed(item_code: string) {
  try {
    const r = await PetBondApi.feed(item_code)
    if (r.data?.data) {
      const next = r.data.data
      animatingDelta.value = next.delta ?? 0
      showHeart.value = true
      setTimeout(() => (showHeart.value = false), 1200)
      state.value = {
        species: next.species,
        bond_xp: next.bond_xp,
        bond_level: next.bond_level,
        intimacy_tier: next.intimacy_tier,
        next_tier_at: next.next_tier_at,
        progress_percent: next.progress_percent,
      }
    }
  } catch {
    // graceful
  }
}

export function usePetBond() {
  return {
    state: computed(() => state.value),
    loading: computed(() => loading.value),
    animatingDelta: computed(() => animatingDelta.value),
    showHeart: computed(() => showHeart.value),
    intimacyKey: computed<string>(() =>
      state.value ? TIER_LABEL[state.value.intimacy_tier] ?? 'bond_tier_stranger' : 'bond_tier_stranger',
    ),
    refresh,
    petHead,
    feed,
  }
}
