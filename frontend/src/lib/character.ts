// 潘朵拉月曆 character renderer (TS port of meal frontend/public/character.js).
//
// 集團共用一套角色 anchor，但 calendar P1 階段只 ship 兩個 PNG anchor：
//   - dodo (NPC 導師，月曆主視覺角色)
//   - rabbit (用戶寵物 default species)
// 後續若補齊集團 11 species PNG（從 @freeco-company/pandora-design-svg），
// 這裡的 ANIMAL_META map 自動可以擴。

export type Species = 'dodo' | 'rabbit' | 'cat' | 'penguin' | 'bear'
export type Mood =
  | 'happy'
  | 'sleeping'
  | 'cheering'
  | 'proud'
  | 'content'
  | 'sad'
  | 'missing_you'

export type Outfit =
  | 'none'
  | 'ribbon'
  | 'witch_hat'
  | 'fp_crown'
  | 'fp_chef'
  | 'straw_hat'
  | 'sakura'
  | 'sunglasses'
  | 'winter_scarf'
  | 'chef_apron'
  | 'fp_apron_premium'
  | 'angel_wings'
  | 'starry_cape'

const ANCHOR_BASE = '/character/anchors'
const OUTFIT_BASE = '/character/outfits'
const MOOD_BASE = '/character/moods'

interface AnimalMeta {
  file: string
  name: string
  halo: string
}

export const ANIMAL_META: Record<Species, AnimalMeta> = {
  dodo: { file: 'dodo.png', name: '朵朵', halo: '#FFE4D2' },
  rabbit: { file: 'rabbit.png', name: '兔兔', halo: '#FCE6E6' },
  // 後備：未 ship 的 species fallback 到 rabbit
  cat: { file: 'rabbit.png', name: '貓貓', halo: '#FFE3D6' },
  penguin: { file: 'rabbit.png', name: '企鵝', halo: '#DDE9F2' },
  bear: { file: 'rabbit.png', name: '熊熊', halo: '#E8D5BB' },
}

interface AnchorPoint {
  eye_y: number
  nose_y: number
  neck_y: number
  chest_y: number
  back_y: number
}

const ANIMAL_ANCHORS: Record<Species, AnchorPoint> = {
  dodo: { eye_y: 38, nose_y: 45, neck_y: 56, chest_y: 68, back_y: 50 },
  rabbit: { eye_y: 38, nose_y: 45, neck_y: 58, chest_y: 70, back_y: 52 },
  cat: { eye_y: 36, nose_y: 43, neck_y: 52, chest_y: 62, back_y: 50 },
  penguin: { eye_y: 28, nose_y: 34, neck_y: 50, chest_y: 62, back_y: 48 },
  bear: { eye_y: 32, nose_y: 39, neck_y: 48, chest_y: 58, back_y: 46 },
}

interface OutfitMeta {
  anchor: 'head_top' | keyof AnchorPoint
  offset_px?: number
  scale: number
  behind?: boolean
  opacity?: number
}

const OUTFIT_ANCHOR: Record<Exclude<Outfit, 'none'>, OutfitMeta> = {
  ribbon: { anchor: 'head_top', offset_px: -4, scale: 0.32 },
  witch_hat: { anchor: 'head_top', offset_px: -32, scale: 0.42 },
  fp_crown: { anchor: 'head_top', offset_px: -18, scale: 0.34 },
  fp_chef: { anchor: 'head_top', offset_px: -28, scale: 0.38 },
  straw_hat: { anchor: 'head_top', offset_px: -10, scale: 0.46 },
  sakura: { anchor: 'head_top', offset_px: -2, scale: 0.4 },
  sunglasses: { anchor: 'eye_y', scale: 0.32 },
  winter_scarf: { anchor: 'neck_y', scale: 0.4 },
  chef_apron: { anchor: 'chest_y', scale: 0.46 },
  fp_apron_premium: { anchor: 'chest_y', scale: 0.46 },
  angel_wings: { anchor: 'back_y', scale: 1.0, behind: true, opacity: 0.9 },
  starry_cape: { anchor: 'back_y', scale: 0.92, behind: true, opacity: 0.92 },
}

const OUTFIT_SRC: Record<Exclude<Outfit, 'none'>, string> = {
  ribbon: 'outfit_ribbon_overlay.svg',
  witch_hat: 'outfit_witch_hat_overlay.svg',
  fp_crown: 'outfit_fp_crown_overlay.svg',
  fp_chef: 'outfit_fp_chef_overlay.svg',
  straw_hat: 'outfit_straw_hat_overlay.svg',
  sakura: 'outfit_sakura_overlay.svg',
  sunglasses: 'outfit_sunglasses_overlay.svg',
  winter_scarf: 'outfit_winter_scarf_overlay.svg',
  chef_apron: 'outfit_chef_apron_overlay.svg',
  fp_apron_premium: 'outfit_fp_apron_premium_overlay.svg',
  angel_wings: 'outfit_angel_wings_overlay.svg',
  starry_cape: 'outfit_starry_cape_overlay.svg',
}

const MOOD_BADGE: Record<Mood, string | null> = {
  happy: 'sparkling_heart.svg',
  sleeping: 'zzz.svg',
  cheering: 'party.svg',
  proud: 'trophy.svg',
  content: null,
  sad: 'pleading.svg',
  missing_you: 'pleading.svg',
}

export function rarityOf(level: number) {
  if (level >= 50)
    return {
      key: 'mythic',
      name: '神話',
      textColor: '#FFFFFF',
      shadow: '0 1px 3px rgba(0,0,0,.35)',
      gradient: 'linear-gradient(135deg, #FFD56E, #FF6EA5 55%, #6EC8FF)',
    }
  if (level >= 20)
    return {
      key: 'legendary',
      name: '傳說',
      textColor: '#FFFFFF',
      shadow: '0 1px 2px rgba(139,90,48,.6)',
      gradient: 'linear-gradient(135deg, #F4D78A, #E89F7A)',
    }
  if (level >= 10)
    return {
      key: 'epic',
      name: '史詩',
      textColor: '#FFFFFF',
      shadow: '0 1px 2px rgba(74,60,80,.6)',
      gradient: 'linear-gradient(135deg, #B89AC9, #7A6A9C)',
    }
  if (level >= 5)
    return {
      key: 'rare',
      name: '稀有',
      textColor: '#FFFFFF',
      shadow: '0 1px 2px rgba(78,106,63,.6)',
      gradient: 'linear-gradient(135deg, #A8C5B4, #7A9B8A)',
    }
  return {
    key: 'common',
    name: '普通',
    textColor: '#5C4E46',
    shadow: 'none',
    gradient: 'linear-gradient(135deg, #FFFFFF, #F5ECD9)',
  }
}

export function accessoryAsset(level: number): string | null {
  if (level >= 50) return 'crown.svg'
  if (level >= 20) return 'ribbon.svg'
  if (level >= 10) return 'cherry.svg'
  return null
}

export interface OutfitStyle {
  src: string
  style: Record<string, string>
  zIndex: number
  className: string
}

export function outfitStyle(outfit: Outfit, species: Species): OutfitStyle | null {
  if (!outfit || outfit === 'none') return null
  const meta = OUTFIT_ANCHOR[outfit as Exclude<Outfit, 'none'>]
  const file = OUTFIT_SRC[outfit as Exclude<Outfit, 'none'>]
  if (!meta || !file) return null
  const anim = ANIMAL_ANCHORS[species] || ANIMAL_ANCHORS.rabbit

  const widthPct = `${(meta.scale * 100).toFixed(1)}%`
  const zIndex = meta.behind ? 1 : meta.anchor === 'head_top' ? 5 : 4
  const opacity = meta.opacity != null ? String(meta.opacity) : '1'
  const offsetPx = meta.offset_px || 0

  let style: Record<string, string>
  if (meta.anchor === 'head_top') {
    style = {
      position: 'absolute',
      top: `${offsetPx}px`,
      left: '50%',
      width: widthPct,
      transform: 'translateX(-50%)',
      zIndex: String(zIndex),
      opacity,
    }
  } else {
    const topPct = anim[meta.anchor as keyof AnchorPoint]
    style = {
      position: 'absolute',
      top: `${topPct}%`,
      left: '50%',
      width: widthPct,
      transform: 'translate(-50%,-50%)',
      zIndex: String(zIndex),
      opacity,
    }
  }
  return {
    src: `${OUTFIT_BASE}/${file}`,
    style,
    zIndex,
    className: `char-outfit of-${outfit.replace(/_/g, '-')}`,
  }
}

export function anchorPath(species: Species): string {
  const meta = ANIMAL_META[species] || ANIMAL_META.rabbit
  return `${ANCHOR_BASE}/${meta.file}`
}

export function moodBadgePath(mood: Mood | null | undefined): string | null {
  if (!mood) return null
  const f = MOOD_BADGE[mood]
  return f ? `${MOOD_BASE}/${f}` : null
}

export function accessoryPath(level: number): string | null {
  const f = accessoryAsset(level)
  return f ? `${MOOD_BASE}/${f}` : null
}

// Phase-driven mood：把週期相位翻成 NPC 表情
export function moodForPhase(phase: string | null | undefined, userMood?: Mood): Mood {
  if (userMood) return userMood
  switch (phase) {
    case 'menstrual':
      return 'missing_you'
    case 'follicular':
      return 'cheering'
    case 'ovulation':
      return 'happy'
    case 'luteal':
      return 'content'
    default:
      return 'happy'
  }
}

// 預設用戶寵物資訊（mock，未接後端前用 localStorage 持久化）
const PET_LS_KEY = 'pandora_calendar_pet'
export interface PetState {
  species: Species
  nickname: string
  level: number
  outfit: Outfit
}
export function getPet(): PetState {
  try {
    const raw = localStorage.getItem(PET_LS_KEY)
    if (raw) return JSON.parse(raw) as PetState
  } catch {
    /* ignore */
  }
  return { species: 'rabbit', nickname: '小兔', level: 3, outfit: 'ribbon' }
}
export function savePet(p: PetState) {
  try {
    localStorage.setItem(PET_LS_KEY, JSON.stringify(p))
  } catch {
    /* ignore */
  }
}
