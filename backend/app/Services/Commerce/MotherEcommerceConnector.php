<?php

namespace App\Services\Commerce;

use App\Models\CycleSymptom;
use App\Models\User;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 婕樂纖商品連結 connector（P5）。
 *
 * 與 ProductLinkResolver 的差異：
 *   - 文案 / 規則 from config('ecommerce-products')，不再 hardcoded
 *   - 每筆 message 過 LegalContentSanitizer::riskReport，紅線命中直接 skip
 *   - **本身不做 gate** — caller（DeepLinkGate / EnsureMotherCustomer middleware）負責
 *     確保只有 eligible user 才能走到這裡
 *
 * 母艦官網的「跳轉商品頁」目前 hardcode 在 config 裡（pandora.js-store.com.tw），
 * P6 接母艦 internal API 後改為動態 fetch。
 */
class MotherEcommerceConnector
{
    public function __construct(private readonly LegalContentSanitizer $sanitizer) {}

    /**
     * @return array<int, array{product_slug: string, product_name: string, message: string, mother_url: string}>
     */
    public function recommendationsFor(User $user): array
    {
        $catalog = config('ecommerce-products', []);
        if (! is_array($catalog) || $catalog === []) {
            return [];
        }

        // 過去 30 天 symptom tag 計數
        $tagCounts = CycleSymptom::where('user_id', $user->id)
            ->where('logged_on', '>=', now()->subDays(30))
            ->get()
            ->pluck('tags')
            ->flatten()
            ->countBy();

        $out = [];
        foreach ($catalog as $row) {
            $trigger = $row['trigger'] ?? null;
            $threshold = (int) ($row['threshold'] ?? 0);
            if (! $trigger || $threshold <= 0) {
                continue;
            }
            if (($tagCounts[$trigger] ?? 0) < $threshold) {
                continue;
            }

            // 合規 gate：紅線詞命中直接跳過此商品（不讓任何違規文案 leak 到前端）
            $message = (string) ($row['message'] ?? '');
            if ($message === '' || $this->sanitizer->riskReport($message) !== []) {
                continue;
            }

            $out[] = [
                'product_slug' => (string) $row['product_slug'],
                'product_name' => (string) ($row['product_name'] ?? ''),
                'message' => $message,
                'mother_url' => (string) ($row['mother_url'] ?? ''),
            ];
        }

        return $out;
    }
}
