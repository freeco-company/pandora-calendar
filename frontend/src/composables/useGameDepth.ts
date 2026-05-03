/**
 * useGameDepth — 整合多個 game-depth 狀態的 facade（rank / skill-path / random-event / solar-term）
 * 所有 fetch 失敗 graceful 不炸；用在 Calendar / Profile / Journey hub。
 */
import { ref, computed } from 'vue'
import {
  RankApi, type RankState,
  SkillPathApi, type SkillPathState,
  RandomEventApi, type RandomEvent,
  SolarTermApi, type SolarTermBanner,
} from '../api'

const rank = ref<RankState | null>(null)
const skillPath = ref<SkillPathState | null>(null)
const randomEvent = ref<RandomEvent | null>(null)
const solarTerm = ref<SolarTermBanner | null>(null)
const loaded = ref(false)

async function refreshAll() {
  const results = await Promise.allSettled([
    RankApi.show(),
    SkillPathApi.show(),
    RandomEventApi.today(),
    SolarTermApi.current(),
  ])
  if (results[0].status === 'fulfilled') rank.value = results[0].value.data?.data ?? null
  if (results[1].status === 'fulfilled') skillPath.value = results[1].value.data?.data ?? null
  if (results[2].status === 'fulfilled') randomEvent.value = results[2].value.data?.data ?? null
  if (results[3].status === 'fulfilled') solarTerm.value = results[3].value.data?.data ?? null
  loaded.value = true
}

async function refreshRank() {
  try {
    const r = await RankApi.show()
    rank.value = r.data?.data ?? null
  } catch {
    /* graceful */
  }
}

async function refreshRandomEvent() {
  try {
    const r = await RandomEventApi.today()
    randomEvent.value = r.data?.data ?? null
  } catch {
    randomEvent.value = null
  }
}

async function refreshSolarTerm() {
  try {
    const r = await SolarTermApi.current()
    solarTerm.value = r.data?.data ?? null
  } catch {
    solarTerm.value = null
  }
}

export function useGameDepth() {
  return {
    rank: computed(() => rank.value),
    skillPath: computed(() => skillPath.value),
    randomEvent: computed(() => randomEvent.value),
    solarTerm: computed(() => solarTerm.value),
    loaded: computed(() => loaded.value),
    refreshAll,
    refreshRank,
    refreshRandomEvent,
    refreshSolarTerm,
    setRandomEvent: (e: RandomEvent | null) => (randomEvent.value = e),
    setSolarTerm: (s: SolarTermBanner | null) => (solarTerm.value = s),
  }
}
