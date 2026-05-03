<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Pet\PetPersonalityResolver;
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

    public function show(Request $request, PetPersonalityResolver $personality): JsonResponse
    {
        $u = $request->user();
        $species = $u->pet_species;

        return response()->json([
            'data' => [
                'species' => $species,
                'nickname' => $u->pet_nickname,
                'level' => (int) ($u->level ?? 1),
                'onboarded' => $u->pet_onboarded_at !== null,
                'available_species' => self::ALLOWED_SPECIES,
                'personality' => $species ? $personality->meta($species) : null,
                'species_catalog' => $this->speciesCatalog($personality),
            ],
        ]);
    }

    /**
     * 給 onboarding picker 用 — 每隻 species 的 description / personality 一次拿到，
     * 前端不用再硬編一份大表。
     */
    private function speciesCatalog(PetPersonalityResolver $personality): array
    {
        $out = [];
        foreach (self::ALLOWED_SPECIES as $s) {
            $meta = $personality->meta($s);
            $out[$s] = [
                'name' => $meta['name'] ?? $s,
                'personality' => $meta['personality'] ?? null,
                'description' => $meta['description'] ?? '',
                'reaction_frequency' => $meta['reaction_frequency'] ?? 'medium',
                'celebration_style' => $meta['celebration_style'] ?? 'warm',
            ];
        }

        return $out;
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
