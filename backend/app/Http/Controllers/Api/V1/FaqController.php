<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/faq
 *
 * 從 config/faq.php 讀 flat entry list（narrative-designer canonical schema），
 * 在這裡分組成 categories[] 給前端 tab UI。
 *
 * Cache 1h（config 不會頻繁改）。
 */
class FaqController extends Controller
{
    private const CATEGORY_LABELS = [
        'usage' => '使用',
        'privacy' => '隱私',
        'subscription' => '訂閱',
        'health' => '健康',
        'getting_started' => '入門',
        'tracking' => '記錄',
        'troubleshooting' => '疑難排解',
    ];

    public function index(): JsonResponse
    {
        $payload = Cache::remember('faq:v2', 3600, function () {
            return ['categories' => $this->buildCategories()];
        });

        return response()->json(['data' => $payload]);
    }

    /**
     * @return array<int, array{id: string, title: string, entries: array<int, mixed>}>
     */
    private function buildCategories(): array
    {
        $entries = (array) (config('faq', []) ?? []);

        // 兼容舊 schema：若 config('faq.categories') 存在，直接回那份
        if (isset($entries['categories']) && is_array($entries['categories'])) {
            return $entries['categories'];
        }

        // canonical：flat list with `category` 欄位
        $grouped = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $cat = $entry['category'] ?? 'usage';
            $grouped[$cat] ??= [
                'id' => $cat,
                'title' => self::CATEGORY_LABELS[$cat] ?? $cat,
                'entries' => [],
            ];
            $grouped[$cat]['entries'][] = [
                'id' => $entry['id'] ?? null,
                'q' => $entry['q'] ?? '',
                'a' => $entry['a'] ?? '',
                'related_links' => $entry['related_links'] ?? [],
            ];
        }

        return array_values($grouped);
    }
}
