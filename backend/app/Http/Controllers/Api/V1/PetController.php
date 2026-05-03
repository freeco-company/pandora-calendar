<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    /**
     * 集團共用 11 species 由 design-svg manifest 鎖定（dodo 是 NPC 不算用戶寵物）。
     */
    public const ALLOWED_SPECIES = [
        'cat', 'rabbit', 'dog', 'fox', 'bear', 'penguin', 'pig', 'sheep', 'dinosaur', 'tiger', 'robot',
    ];

    public function show(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'data' => [
                'species' => $u->pet_species,
                'nickname' => $u->pet_nickname,
                'level' => (int) ($u->level ?? 1),
                'onboarded' => $u->pet_onboarded_at !== null,
                'available_species' => self::ALLOWED_SPECIES,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'species' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_SPECIES)],
            'nickname' => ['required', 'string', 'min:1', 'max:32'],
        ]);

        $u = $request->user();
        $u->pet_species = $data['species'];
        $u->pet_nickname = $data['nickname'];
        if ($u->pet_onboarded_at === null) {
            $u->pet_onboarded_at = now();
        }
        $u->save();

        return response()->json([
            'data' => [
                'species' => $u->pet_species,
                'nickname' => $u->pet_nickname,
                'onboarded' => true,
            ],
        ]);
    }
}
