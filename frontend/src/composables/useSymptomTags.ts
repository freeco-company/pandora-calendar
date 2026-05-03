import { ref } from 'vue'
import { api } from '../api'

export type SymptomCategory = 'body' | 'mood' | 'intimacy' | 'fertility'

export interface SymptomTag {
  value: string
  label: string
  emoji: string
  category: SymptomCategory
}

export interface SymptomCategoryGroup {
  key: SymptomCategory
  title: string
  emoji: string
  tags: SymptomTag[]
}

/**
 * Fallback hard-coded list — 對齊 backend `config/symptom-tags.php` 擴充後的 22 個 tag。
 * 後端 endpoint /v1/symptom-tags 上線後，會優先以 API 回傳為主；fallback 在此確保
 * frontend 即便後端尚未提供也能跑（P0 上架前緩衝）。
 *
 * TODO(backend): 提供 GET /api/v1/symptom-tags
 *   回傳格式：{ data: SymptomTag[] }（每個含 value / label / emoji / category）
 */
const FALLBACK_TAGS: SymptomTag[] = [
  // 身體 body（10）
  { value: 'cramp', label: '經痛', emoji: '🌀', category: 'body' },
  { value: 'headache', label: '頭痛', emoji: '🤕', category: 'body' },
  { value: 'fatigue', label: '疲倦', emoji: '😴', category: 'body' },
  { value: 'bloating', label: '腹脹', emoji: '🎈', category: 'body' },
  { value: 'breast_tender', label: '胸脹', emoji: '💗', category: 'body' },
  { value: 'acne', label: '冒痘', emoji: '🫧', category: 'body' },
  { value: 'back_pain', label: '腰痠', emoji: '💢', category: 'body' },
  { value: 'insomnia', label: '失眠', emoji: '🌙', category: 'body' },
  { value: 'nausea', label: '想吐', emoji: '🤢', category: 'body' },
  { value: 'dizziness', label: '頭暈', emoji: '💫', category: 'body' },

  // 情緒 mood（5）
  { value: 'mood_swing', label: '情緒起伏', emoji: '🎢', category: 'mood' },
  { value: 'anxiety', label: '焦慮', emoji: '😟', category: 'mood' },
  { value: 'irritable', label: '易怒', emoji: '😤', category: 'mood' },
  { value: 'sad', label: '低落', emoji: '🥺', category: 'mood' },
  { value: 'craving_sweet', label: '想吃甜', emoji: '🍫', category: 'mood' },

  // 親密 intimacy（4）
  { value: 'libido_high', label: '性慾高', emoji: '💞', category: 'intimacy' },
  { value: 'libido_low', label: '性慾低', emoji: '🌫', category: 'intimacy' },
  { value: 'intercourse_protected', label: '有性行為（避孕）', emoji: '🛡', category: 'intimacy' },
  { value: 'intercourse_unprotected', label: '有性行為（未避孕）', emoji: '🌱', category: 'intimacy' },

  // 生育 fertility（3）
  { value: 'discharge', label: '分泌物', emoji: '💧', category: 'fertility' },
  { value: 'pregnancy_test', label: '驗孕', emoji: '🔬', category: 'fertility' },
  { value: 'contraception', label: '避孕措施', emoji: '💊', category: 'fertility' },
]

const CATEGORY_META: Record<SymptomCategory, { title: string; emoji: string }> = {
  body: { title: '身體', emoji: '🌸' },
  mood: { title: '情緒', emoji: '💭' },
  intimacy: { title: '親密', emoji: '💞' },
  fertility: { title: '生育', emoji: '🌱' },
}

const CATEGORY_ORDER: SymptomCategory[] = ['body', 'mood', 'intimacy', 'fertility']

export function useSymptomTags() {
  const tags = ref<SymptomTag[]>(FALLBACK_TAGS)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get<{ data: SymptomTag[] }>('/v1/symptom-tags')
      if (Array.isArray(data?.data) && data.data.length > 0) {
        tags.value = data.data
      }
    } catch (e: any) {
      // fallback 已存在，靜默
      error.value = e?.response?.data?.message ?? null
    } finally {
      loading.value = false
    }
  }

  function grouped(): SymptomCategoryGroup[] {
    return CATEGORY_ORDER.map((key) => ({
      key,
      title: CATEGORY_META[key].title,
      emoji: CATEGORY_META[key].emoji,
      tags: tags.value.filter((t) => t.category === key),
    })).filter((g) => g.tags.length > 0)
  }

  function labelOf(value: string): string {
    return tags.value.find((t) => t.value === value)?.label ?? value
  }

  return { tags, loading, error, load, grouped, labelOf }
}
