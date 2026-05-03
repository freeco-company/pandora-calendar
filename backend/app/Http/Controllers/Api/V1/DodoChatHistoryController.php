<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DodoCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P1-5 — 朵朵聊天歷史 timeline。給 Dodo tab 顯示「過往對話」感受。
 *
 * 目前內容 = 過往 dodo_checkins。未來 P4 接 LLM chat 時，這層接 chat_messages 表。
 */
class DodoChatHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $rows = DodoCheckin::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('checked_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'checked_on', 'mood', 'phase', 'cycle_day', 'dodo_response']);

        return response()->json(['data' => $rows]);
    }
}
