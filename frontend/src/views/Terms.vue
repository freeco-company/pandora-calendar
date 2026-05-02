<script setup lang="ts">
import { useRouter } from 'vue-router'
import Card from '../components/ui/Card.vue'

const router = useRouter()
</script>

<template>
  <div class="px-5 pt-10 pb-12 max-w-md mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">← 返回</button>
    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Terms of Use</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">使用條款</h1>
      <p class="font-zen text-[11px] text-stone-400 mt-1">最後更新：2026-05-03</p>
    </header>

    <Card tone="plain" class="space-y-3 text-sm font-zen text-stone-700 leading-relaxed">
      <h2 class="font-display font-bold text-peach-500 text-base">服務說明</h2>
      <p class="text-stone-600 text-[13px]">
        潘朵拉月曆提供月經週期記錄、預測與健康節律分析。本服務不構成醫療建議，亦不取代專業醫師診斷。如有身體不適請諮詢醫療專業人員。
      </p>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">使用者義務</h2>
      <ul class="list-disc list-inside space-y-1 text-stone-600 text-[13px]">
        <li>確保註冊資訊真實</li>
        <li>不得將帳號借予他人或拿來測試自動化攻擊</li>
        <li>不得對本服務進行逆向工程或干擾正常運作</li>
      </ul>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">訂閱與付費</h2>
      <p class="text-stone-600 text-[13px]">
        訂閱方案於 App Store / Google Play 內購買。退款依 Apple / Google 各自政策處理；本服務不直接受理退款。
      </p>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">免責</h2>
      <p class="text-stone-600 text-[13px]">
        本服務中朵朵 NPC 提供的建議僅為參考，不代表醫療意見。週期預測基於妳輸入的歷史資料，準確度受個體生理差異影響。
      </p>

      <h2 class="font-display font-bold text-peach-500 text-base mt-4">服務變更</h2>
      <p class="text-stone-600 text-[13px]">
        本條款若有重大變更會於本頁公告，繼續使用視為同意更新。
      </p>

      <p class="text-stone-500 text-[12px] pt-3 border-t border-cream-200">
        服務提供者：婕樂纖（Fairy Pandora）<br />
        聯絡信箱：<a href="mailto:support@js-store.com.tw" class="text-peach-500 underline">support@js-store.com.tw</a>
      </p>
    </Card>
  </div>
</template>
