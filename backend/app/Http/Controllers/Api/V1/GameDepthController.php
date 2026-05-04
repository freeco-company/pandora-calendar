<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Economy\DodoCoinService;
use App\Services\Gamification\BodyDexService;
use App\Services\Gamification\RandomEventService;
use App\Services\Gamification\RankService;
use App\Services\Gamification\SkillPathService;
use App\Services\Gamification\SolarTermService;
use App\Services\Gamification\StoryChapterService;
use App\Services\Pet\PetBondService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave 13 — 深度遊戲化整合 controller。
 *
 * 集中 rank / skill-path / body-dex / story / random-event / solar-term endpoints，
 * pet bond endpoints 也放這（取代既有 PetController bond 部分）。
 */
class GameDepthController extends Controller
{
    public function __construct(
        private readonly RankService $ranks,
        private readonly SkillPathService $paths,
        private readonly BodyDexService $bodyDex,
        private readonly StoryChapterService $stories,
        private readonly RandomEventService $randomEvents,
        private readonly SolarTermService $solarTerms,
        private readonly PetBondService $petBond,
        private readonly DodoCoinService $coins,
    ) {}

    public function rank(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->ranks->currentRank((int) $user->id)]);
    }

    public function skillPath(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->paths->current((int) $user->id);

        return response()->json([
            'data' => [
                'path' => $current?->path,
                'chosen_at' => $current?->chosen_at?->toIso8601String(),
                'last_changed_at' => $current?->last_changed_at?->toIso8601String(),
                'available_paths' => SkillPathService::VALID_PATHS,
            ],
        ]);
    }

    public function chooseSkillPath(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'in:'.implode(',', SkillPathService::VALID_PATHS)],
        ]);
        $user = $request->user();

        try {
            $row = $this->paths->choose((int) $user->id, $data['path']);
        } catch (\DomainException $e) {
            return response()->json(['errors' => ['path' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => [
            'path' => $row->path,
            'chosen_at' => $row->chosen_at?->toIso8601String(),
            'last_changed_at' => $row->last_changed_at?->toIso8601String(),
        ]]);
    }

    public function skillPathQuests(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->paths->quests((int) $user->id)]);
    }

    public function bodyDex(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => $this->bodyDex->snapshot((int) $user->id)]);
    }

    public function storyChapters(Request $request): JsonResponse
    {
        $user = $request->user();
        $unlocked = $this->stories->unlocked((int) $user->id);
        $chapters = $this->stories->chapters();
        $rows = [];
        foreach ($chapters as $c) {
            $chapter = (int) $c['chapter'];
            $rows[] = [
                'chapter' => $chapter,
                'title' => $c['title'],
                'summary' => $c['summary'],
                'unlock_cycle' => (int) ($c['unlock_cycle'] ?? max(0, $chapter - 1)),
                'coin_cost' => (int) ($c['coin_cost'] ?? 100),
                'is_unlocked' => in_array($chapter, $unlocked, true),
            ];
        }

        return response()->json(['data' => [
            'chapters' => $rows,
            'unlocked_count' => count($unlocked),
            'total' => count($chapters),
        ]]);
    }

    public function unlockStoryChapter(Request $request, int $chapter): JsonResponse
    {
        $user = $request->user();
        $result = $this->stories->unlockWithCoinsResult((int) $user->id, $chapter);

        if ($result === 'success') {
            return response()->json(['data' => ['chapter' => $chapter, 'unlocked' => true]]);
        }

        // friendly error message + reason code 給 frontend 切文案
        $message = match ($result) {
            'already_unlocked' => '這個章節已經解鎖了，可以直接看 💛',
            'not_found' => '找不到這個章節',
            'insufficient_balance' => '朵朵幣不夠喔，記錄一些事情累積一下再回來看吧',
            default => '解鎖失敗，等等再試',
        };

        return response()->json([
            'errors' => ['chapter' => [$result]],
            'message' => $message,
            'reason' => $result,
        ], 422);
    }

    public function readStoryChapter(Request $request, int $chapter): JsonResponse
    {
        $user = $request->user();
        if (! $this->stories->isUnlocked((int) $user->id, $chapter)) {
            return response()->json(['errors' => ['chapter' => ['not_unlocked']]], 403);
        }
        $this->stories->markRead((int) $user->id, $chapter);
        $meta = $this->stories->chapter($chapter);

        return response()->json(['data' => $meta]);
    }

    public function todayRandomEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        $log = $this->randomEvents->todayLog((int) $user->id);
        if ($log === null) {
            $log = $this->randomEvents->roll((int) $user->id);
        }
        if ($log === null) {
            return response()->json(['data' => null]);
        }
        $event = $this->randomEvents->eventByKey($log->event_key);

        return response()->json(['data' => [
            'id' => $log->id,
            'event_key' => $log->event_key,
            'title' => $event['title'] ?? '',
            'description' => $event['description'] ?? '',
            'reward_coins' => (int) $log->reward_coins,
            'reward_xp' => (int) $log->reward_xp,
            'claimed' => (bool) $log->claimed,
            'triggered_at' => $log->triggered_at?->toIso8601String(),
        ]]);
    }

    public function claimRandomEvent(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $log = $this->randomEvents->claim((int) $user->id, $id);
        if ($log === null) {
            return response()->json(['errors' => ['event' => ['not_found_or_already_claimed']]], 422);
        }

        return response()->json(['data' => [
            'id' => $log->id,
            'claimed' => true,
            'reward_coins' => (int) $log->reward_coins,
            'balance' => $this->coins->balance((int) $user->id),
        ]]);
    }

    public function currentSolarTerm(): JsonResponse
    {
        return response()->json(['data' => $this->solarTerms->currentTerm()]);
    }

    public function participateSolarTerm(Request $request, string $term): JsonResponse
    {
        $user = $request->user();
        $row = $this->solarTerms->participate((int) $user->id, $term);
        if ($row === null) {
            return response()->json(['errors' => ['term' => ['outside_window_or_already_participated']]], 422);
        }

        return response()->json(['data' => [
            'term_key' => $row->term_key,
            'year' => (int) $row->year,
            'earned_coins' => (int) $row->earned_coins,
            'balance' => $this->coins->balance((int) $user->id),
        ]]);
    }

    public function petBond(Request $request): JsonResponse
    {
        $user = $request->user();
        $species = $user->pet_species;
        if (empty($species)) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->petBond->snapshot((int) $user->id, $species)]);
    }

    public function feedPet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_code' => ['nullable', 'string', 'max:32'],
        ]);
        $user = $request->user();
        $species = $user->pet_species;
        if (empty($species)) {
            return response()->json(['errors' => ['pet' => ['no_pet_selected']]], 422);
        }
        $bond = $this->petBond->feed((int) $user->id, $species, $data['item_code'] ?? 'default');
        if ($bond === null) {
            return response()->json([
                'errors' => ['feed' => ['daily_limit_or_insufficient_balance']],
            ], 422);
        }

        return response()->json(['data' => $this->petBond->snapshot((int) $user->id, $species)]);
    }

    public function petHead(Request $request): JsonResponse
    {
        $user = $request->user();
        $species = $user->pet_species;
        if (empty($species)) {
            return response()->json(['errors' => ['pet' => ['no_pet_selected']]], 422);
        }
        $bond = $this->petBond->petHead((int) $user->id, $species);
        if ($bond === null) {
            return response()->json([
                'errors' => ['pet_head' => ['daily_limit_reached']],
            ], 422);
        }

        return response()->json(['data' => $this->petBond->snapshot((int) $user->id, $species)]);
    }
}
