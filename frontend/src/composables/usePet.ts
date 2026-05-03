// 共用 pet ref：自動同步 LS / 後端 PetApi.show 結果 / pandora:pet-updated 事件。
// 用於 Profile / Calendar / JourneyView / GamificationStrip — 任一處改寵物，所有頁籤即時 reflect。
import { onMounted, onUnmounted, ref } from 'vue'
import { getPet, savePet, type PetState, type Species } from '../lib/character'
import { PetApi } from '../api'

export function usePet() {
  const pet = ref<PetState>(getPet())

  function applyServerPet(data: {
    species: string | null
    nickname: string | null
  }) {
    if (!data.species) return
    const merged: PetState = {
      ...pet.value,
      species: data.species as Species,
      nickname: data.nickname ?? pet.value.nickname,
    }
    pet.value = merged
    savePet(merged)
  }

  async function refreshFromServer() {
    try {
      const res = await PetApi.show()
      applyServerPet(res.data.data)
    } catch {
      // 後端不可達時 fallback 留 LS 既有值（avoid 閃白）
    }
  }

  function onUpdated(e: Event) {
    const detail = (e as CustomEvent<PetState>).detail
    if (detail) pet.value = detail
    else pet.value = getPet()
  }

  onMounted(() => {
    window.addEventListener('pandora:pet-updated', onUpdated)
    refreshFromServer()
  })
  onUnmounted(() => {
    window.removeEventListener('pandora:pet-updated', onUpdated)
  })

  return { pet, refreshFromServer }
}
