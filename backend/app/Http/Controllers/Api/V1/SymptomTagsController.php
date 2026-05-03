<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * 提供 symptom tag canonical 給前端。回傳結構分 4 類，
 * key 由 backend 定義，前端不要 hard-code。
 */
class SymptomTagsController extends Controller
{
    public function index(): JsonResponse
    {
        $cfg = config('symptom-tags', []);

        return response()->json([
            'data' => [
                'physical' => $cfg['physical'] ?? [],
                'emotional' => $cfg['emotional'] ?? [],
                'sexual' => $cfg['sexual'] ?? [],
                'fertility' => $cfg['fertility'] ?? [],
            ],
        ]);
    }
}
