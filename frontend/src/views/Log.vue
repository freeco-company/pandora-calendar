<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { CalendarApi, type CycleRecord, type SymptomRecord } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useSfx } from '../lib/sound'
import { awardXp, emitAchievement, consumeGamificationPending } from '../lib/gamification'
import { useSymptomTags, type SymptomCategory } from '../composables/useSymptomTags'
import { useTone } from '../composables/useTone'

const { t } = useTone()
const sfx = useSfx()
const startDate = ref(new Date().toISOString().slice(0, 10))
const endDate = ref('')
const peakFlow = ref(3)
const symptomDate = ref(new Date().toISOString().slice(0, 10))
const selectedTags = ref<string[]>([])
const symptomMood = ref<string>('okay')
const cycles = ref<CycleRecord[]>([])
const symptoms = ref<SymptomRecord[]>([])
const saving = ref(false)
const message = ref<string | null>(null)

const symptomTags = useSymptomTags()
const expandedCategories = ref<Record<SymptomCategory, boolean>>({
  body: true,
  mood: true,
  intimacy: false,
  fertility: false,
})

function toggleCategory(key: SymptomCategory) {
  expandedCategories.value[key] = !expandedCategories.value[key]
  sfx.play('ui_tap')
}

async function load() {
  const [c, s] = await Promise.all([CalendarApi.cycles(), CalendarApi.symptoms()])
  cycles.value = c.data.data
  symptoms.value = s.data.data
}
onMounted(() => {
  symptomTags.load()
  load()
})

async function saveCycle() {
  saving.value = true
  message.value = null
  try {
    await CalendarApi.storeCycle({
      start_date: startDate.value,
      end_date: endDate.value || undefined,
      peak_flow: peakFlow.value,
    })
    message.value = t('log_toast_cycle_saved')
    sfx.play('cycle_logged')
    // 樂觀 +XP（對齊 py-service catalog: calendar.cycle_logged = 5 XP）
    awardXp(5, t('log_xp_cycle_logged'))
    if (cycles.value.length === 0) {
      // 本地樂觀 first_cycle 成就 toast（py-service 真正 award 透過 webhook 到 pending，
      // consumeGamificationPending 會在後面消化；重複觸發無害，icon 一致）
      setTimeout(() => {
        emitAchievement({
          code: 'first_cycle',
          title: t('log_first_cycle_title'),
          description: t('log_first_cycle_desc'),
          icon: '🌸',
        })
      }, 700)
    }
    await load()
    // 真實 level_up / achievement_unlocked 由 py-service webhook 推回 → cache → 這裡 pull
    setTimeout(() => { void consumeGamificationPending() }, 1500)
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? t('log_error_save_failed')
    sfx.play('wrong')
  } finally {
    saving.value = false
  }
}

function toggleTag(t: string) {
  sfx.play('choice_select')
  const idx = selectedTags.value.indexOf(t)
  if (idx >= 0) selectedTags.value.splice(idx, 1)
  else selectedTags.value.push(t)
}

async function saveSymptom() {
  saving.value = true
  message.value = null
  try {
    await CalendarApi.storeSymptom({
      logged_on: symptomDate.value,
      tags: selectedTags.value,
      mood: symptomMood.value,
    })
    message.value = t('log_toast_symptom_saved')
    sfx.play('meal_logged')
    // 樂觀 +XP（對齊 catalog: calendar.symptom_logged = 3 XP）
    awardXp(3, t('log_xp_symptom_logged'))
    selectedTags.value = []
    await load()
    setTimeout(() => { void consumeGamificationPending() }, 1500)
  } catch (e: any) {
    message.value = e?.response?.data?.message ?? t('log_error_save_failed')
    sfx.play('wrong')
  } finally {
    saving.value = false
  }
}

function pickMood(v: string) {
  sfx.play('choice_select')
  symptomMood.value = v
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-6 max-w-md md:max-w-4xl lg:max-w-5xl mx-auto space-y-5">
    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('log_eyebrow') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ t('log_title_today') }}</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">{{ t('log_subtitle') }}</p>
    </header>

    <div class="md:grid md:grid-cols-2 md:gap-5 md:items-start space-y-5 md:space-y-0">
    <Card tone="plain" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">🌙</span>
        <h2 class="font-display font-bold text-peach-500 text-base">{{ t('log_section_period') }}</h2>
      </div>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <label class="block">
          <span class="text-stone-500 font-zen text-xs">{{ t('log_field_start_date') }}</span>
          <input
            v-model="startDate"
            type="date"
            data-test="cycle-start-date"
            class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
          />
        </label>
        <label class="block">
          <span class="text-stone-500 font-zen text-xs">{{ t('log_field_end_date') }}</span>
          <input
            v-model="endDate"
            type="date"
            data-test="cycle-end-date"
            class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
          />
        </label>
      </div>
      <label class="block text-sm">
        <span class="text-stone-500 font-zen text-xs">{{ t('log_field_flow_label') }}</span>
        <div class="flex items-center gap-3 mt-2">
          <input
            v-model.number="peakFlow"
            type="range"
            min="1"
            max="5"
            class="flex-1 accent-peach-400"
          />
          <span class="text-peach-500 font-display font-bold text-lg w-6 text-center">{{ peakFlow }}</span>
        </div>
      </label>
      <Button
        full
        variant="primary"
        :loading="saving"
        data-test="save-cycle"
        sfx="cycle_logged"
        @click="saveCycle"
      >
        {{ t('log_btn_save_cycle') }}
      </Button>
    </Card>

    <Card tone="plain" class="space-y-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">🌸</span>
        <h2 class="font-display font-bold text-peach-500 text-base">{{ t('log_section_today_state') }}</h2>
      </div>
      <label class="block text-sm">
        <span class="text-stone-500 font-zen text-xs">{{ t('log_field_date') }}</span>
        <input
          v-model="symptomDate"
          type="date"
          class="mt-1 w-full px-3 py-2.5 rounded-2xl border border-cream-200 bg-cream-50 focus:outline-none focus:border-peach-300 focus:bg-white transition-colors text-sm"
        />
      </label>
      <div class="space-y-2.5">
        <div
          v-for="group in symptomTags.grouped()"
          :key="group.key"
          class="border border-cream-200 rounded-2xl overflow-hidden bg-white"
        >
          <button
            type="button"
            @click="toggleCategory(group.key)"
            :data-test="`tag-cat-${group.key}`"
            class="w-full px-3.5 py-2.5 flex items-center justify-between bg-cream-50 hover:bg-peach-50 transition-colors"
          >
            <span class="font-zen text-sm text-peach-500 flex items-center gap-1.5">
              <span>{{ group.emoji }}</span>
              <span>{{ group.title }}</span>
              <span class="text-[10px] text-stone-400">{{ group.tags.length }}</span>
            </span>
            <span class="text-stone-400 text-xs">
              {{ expandedCategories[group.key] ? '−' : '+' }}
            </span>
          </button>
          <div
            v-if="expandedCategories[group.key]"
            class="flex flex-wrap gap-2 p-3"
          >
            <button
              v-for="t in group.tags"
              :key="t.value"
              @click="toggleTag(t.value)"
              :data-test="`tag-${t.value}`"
              class="px-3 py-1.5 rounded-full text-xs font-zen border transition-all active:scale-95 flex items-center gap-1"
              :class="
                selectedTags.includes(t.value)
                  ? 'bg-peach-gradient text-white border-transparent shadow-soft'
                  : 'bg-cream-50 text-peach-500 border-cream-200 hover:bg-peach-50'
              "
            >
              <span>{{ t.emoji }}</span>
              <span>{{ t.label }}</span>
            </button>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="m in [
            { v: 'good', e: '😊', label: t('log_mood_good') },
            { v: 'okay', e: '😐', label: t('log_mood_okay') },
            { v: 'bad', e: '😞', label: t('log_mood_bad') },
          ]"
          :key="m.v"
          @click="pickMood(m.v)"
          class="flex flex-col items-center gap-1 py-3 rounded-2xl border transition-all active:scale-95"
          :class="
            symptomMood === m.v
              ? 'bg-peach-50 border-peach-300 shadow-soft'
              : 'bg-white border-cream-200 hover:bg-cream-50'
          "
        >
          <span class="text-2xl">{{ m.e }}</span>
          <span class="text-[11px] font-zen text-stone-600">{{ m.label }}</span>
        </button>
      </div>
      <Button
        full
        variant="secondary"
        :loading="saving"
        data-test="save-symptom"
        sfx="meal_logged"
        @click="saveSymptom"
      >
        {{ t('log_btn_save_symptom') }}
      </Button>
    </Card>
    </div>

    <p
      v-if="message"
      data-test="save-message"
      class="text-center text-sm text-peach-500 font-zen animate-fadein"
    >
      {{ message }}
    </p>

    <Card tone="plain">
      <h2 class="font-display font-bold text-peach-500 text-base mb-3 flex items-center gap-2">
        <span class="text-xl">📚</span> {{ t('log_section_recent') }}
      </h2>
      <ul v-if="cycles.length" class="text-sm divide-y divide-cream-200 font-zen">
        <li
          v-for="c in cycles.slice(0, 6)"
          :key="c.id"
          class="py-2.5 flex justify-between text-stone-600"
        >
          <span>{{ c.start_date }}</span>
          <span class="text-stone-400">{{ c.length_days ?? t('log_recent_in_progress') }}{{ c.length_days ? ' ' + t('calendar_unit_day') : '' }}</span>
        </li>
      </ul>
      <EmptyState
        v-else
        icon="🌱"
        :title="t('log_empty_title')"
        :subtitle="t('log_empty_subtitle')"
      />
    </Card>
  </div>
</template>
