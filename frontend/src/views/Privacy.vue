<script setup lang="ts">
import { useRouter } from 'vue-router'
import Card from '../components/ui/Card.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">{{ t('common_back') }}</button>
    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ t('privacy_eyebrow') }}</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ t('privacy_title') }}</h1>
      <p class="font-zen text-[11px] text-stone-400 mt-1">{{ t('privacy_last_updated') }}</p>
    </header>

    <Card tone="plain" class="space-y-3 text-sm font-zen text-stone-700 leading-relaxed">
      <h2 class="font-display font-bold text-peach-500 text-base">我們蒐集什麼</h2>
      <ul class="list-disc list-inside space-y-1 text-stone-600 text-[13px]">
        <li>身份識別碼（UUID）— 由集團 Pandora Core 統一發放</li>
        <li>週期記錄、症狀標記、心情、體溫、體重等妳主動輸入的健康數據</li>
        <li>使用統計（記錄頻率、連勝天數）— 用於遊戲化提醒</li>
      </ul>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">我們不蒐集什麼（紅線）</h2>
      <ul class="list-disc list-inside space-y-1 text-stone-600 text-[13px]">
        <li>❌ 不在本機儲存 email / 電話 / 密碼（一律存在集團 Pandora Core）</li>
        <li>❌ 不蒐集位置、聯絡人、相簿、麥克風</li>
        <li>❌ 不向第三方廣告商分享資料</li>
        <li>❌ 不販售妳的健康資料</li>
      </ul>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">資料保存</h2>
      <p class="text-stone-600 text-[13px]">
        妳的週期資料保存於台灣本地伺服器（Linode Tokyo 機房）。妳隨時能在「我的 → 刪除我的月曆資料」一鍵清除全部記錄。
      </p>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">第三方</h2>
      <p class="text-stone-600 text-[13px]">
        登入透過集團 Pandora Core Identity（不傳給第三方）。Google / LINE / Apple 登入僅用於身份驗證；我們不主動讀取除信箱外的個人資訊。
      </p>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">聯絡我們</h2>
      <p class="text-stone-600 text-[13px]">
        若妳對隱私處理有疑問，請來信
        <a href="mailto:support@js-store.com.tw" class="text-peach-500 underline">support@js-store.com.tw</a>。
      </p>
    </Card>
  </div>
</template>
