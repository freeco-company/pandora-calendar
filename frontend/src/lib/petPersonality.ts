// Pet species personality matrix（前端鏡像）。
//
// source of truth = backend/config/pet-species.php；兩邊 keys / description 必須一致。
// 為什麼前端有副本：UI hint（onboarding picker / profile pet card）每秒都在用，
// 沒必要打 API；description 屬靜態 UI 文案不是 PII。
import type { Species } from './character'

export type CelebrationStyle = 'subtle' | 'warm' | 'energetic' | 'playful'
export type ReactionFrequency = 'low' | 'medium' | 'high'

export interface SpeciesPersonality {
  name: string
  personality: string
  reactionFrequency: ReactionFrequency
  celebrationStyle: CelebrationStyle
  preferredPhase: 'menstrual' | 'follicular' | 'ovulation' | 'luteal'
  description: string
}

export const SPECIES_PERSONALITY: Record<Species, SpeciesPersonality> = {
  // dodo 是集團 NPC，不會被 user 選為寵物，但保留以避免 type 報錯
  dodo: {
    name: '朵朵',
    personality: 'mentor_npc',
    reactionFrequency: 'medium',
    celebrationStyle: 'warm',
    preferredPhase: 'menstrual',
    description: '集團導師朵朵 — 不是寵物，是陪在妳身邊的小幫手。',
  },
  cat: {
    name: '貓貓',
    personality: 'gentle_observer',
    reactionFrequency: 'low',
    celebrationStyle: 'subtle',
    preferredPhase: 'luteal',
    description: '安靜陪在妳身邊，不打擾但都看在眼裡。',
  },
  rabbit: {
    name: '兔兔',
    personality: 'gentle_supporter',
    reactionFrequency: 'medium',
    celebrationStyle: 'warm',
    preferredPhase: 'follicular',
    description: '害羞但很想對妳好，會記得妳每個小變化。',
  },
  // (集團共用 11 species，沒有 hamster — design-svg manifest 鎖定)
  bear: {
    name: '熊熊',
    personality: 'warm_hugger',
    reactionFrequency: 'medium',
    celebrationStyle: 'warm',
    preferredPhase: 'menstrual',
    description: '大大的擁抱型，妳累了就靠著它。',
  },
  penguin: {
    name: '企鵝',
    personality: 'calm_thinker',
    reactionFrequency: 'low',
    celebrationStyle: 'subtle',
    preferredPhase: 'luteal',
    description: '冷靜不慌張，像妳的理性朋友。',
  },
  dog: {
    name: '狗狗',
    personality: 'loyal_companion',
    reactionFrequency: 'high',
    celebrationStyle: 'playful',
    preferredPhase: 'ovulation',
    description: '黏人又忠誠，妳走到哪它跟到哪。',
  },
  fox: {
    name: '狐狸',
    personality: 'curious_clever',
    reactionFrequency: 'medium',
    celebrationStyle: 'playful',
    preferredPhase: 'follicular',
    description: '機靈又好奇，會發現妳沒注意到的小細節。',
  },
  pig: {
    name: '豬豬',
    personality: 'cozy_foodie',
    reactionFrequency: 'medium',
    celebrationStyle: 'warm',
    preferredPhase: 'luteal',
    description: '愛吃愛睡，是最會陪妳放鬆的那一個。',
  },
  sheep: {
    name: '羊羊',
    personality: 'soft_dreamer',
    reactionFrequency: 'low',
    celebrationStyle: 'subtle',
    preferredPhase: 'luteal',
    description: '柔軟像雲，幫妳把心情撫平。',
  },
  dinosaur: {
    name: '小恐龍',
    personality: 'wild_supporter',
    reactionFrequency: 'high',
    celebrationStyle: 'energetic',
    preferredPhase: 'ovulation',
    description: '看起來野，其實只想保護妳。',
  },
  tiger: {
    name: '小老虎',
    personality: 'bold_protector',
    reactionFrequency: 'medium',
    celebrationStyle: 'energetic',
    preferredPhase: 'follicular',
    description: '威風但溫柔，為妳擋下小麻煩。',
  },
  robot: {
    name: '機器人',
    personality: 'precise_logician',
    reactionFrequency: 'medium',
    celebrationStyle: 'subtle',
    preferredPhase: 'follicular',
    description: '把妳的節奏記錄得清清楚楚，溫柔的理性派。',
  },
}

export function getSpeciesPersonality(species: Species | string | null | undefined): SpeciesPersonality {
  if (!species) return SPECIES_PERSONALITY.rabbit
  return SPECIES_PERSONALITY[species as Species] ?? SPECIES_PERSONALITY.rabbit
}

// idle bounce frequency（毫秒間隔）— Character.vue idle animation hint
export function idleBounceMs(species: Species): number {
  const f = SPECIES_PERSONALITY[species]?.reactionFrequency ?? 'medium'
  switch (f) {
    case 'high': return 1800
    case 'low': return 6000
    default: return 3500
  }
}
