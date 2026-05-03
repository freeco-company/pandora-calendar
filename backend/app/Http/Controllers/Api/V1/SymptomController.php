<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CycleSymptom;
use App\Services\Gamification\BodyDexService;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\IdempotencyKey;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SymptomController extends Controller
{
    public function __construct(
        private readonly GamificationPublisher $gamification,
        private readonly BodyDexService $bodyDex,
    ) {}

    /**
     * Allowed tag keys 走 config/symptom-tags.php（22+，narrative agent 可擴 key）。
     * 保留 const 是為了 fallback；若 config 沒載入仍能維持基本 10 tag 不破測試。
     */
    public const ALLOWED_TAGS = [
        'cramp', 'headache', 'fatigue', 'bloating', 'breast_tender',
        'acne', 'mood_swing', 'craving_sweet', 'insomnia', 'back_pain',
    ];

    private function allowedTagsFromConfig(): array
    {
        $cfg = config('symptom-tags');
        if (! is_array($cfg) || empty($cfg)) {
            return self::ALLOWED_TAGS;
        }

        $keys = [];
        foreach ($cfg as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group as $tag) {
                if (isset($tag['key']) && is_string($tag['key'])) {
                    $keys[] = $tag['key'];
                }
            }
        }

        return $keys ?: self::ALLOWED_TAGS;
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = CycleSymptom::where('user_id', $request->user()->id)
            ->orderByDesc('logged_on');

        if (! empty($data['from'])) {
            $query->where('logged_on', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->where('logged_on', '<=', $data['to']);
        }

        return response()->json([
            'data' => $query->limit(180)->get()->map(fn (CycleSymptom $s) => [
                'id' => $s->id,
                'logged_on' => $s->logged_on->toDateString(),
                'tags' => $s->tags,
                'mood' => $s->mood,
                'basal_temperature' => $s->basal_temperature,
                'note' => $s->note,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logged_on' => ['required', 'date'],
            'tags' => ['array'],
            'tags.*' => ['string', 'in:'.implode(',', $this->allowedTagsFromConfig())],
            'mood' => ['nullable', 'string', 'in:good,okay,bad'],
            'basal_temperature' => ['nullable', 'numeric', 'between:34,42'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $symptom = CycleSymptom::updateOrCreate(
            ['user_id' => $user->id, 'logged_on' => $data['logged_on']],
            $data + ['tags' => $data['tags'] ?? []],
        );

        // P5.2 ADR-009：tags 非空 → symptom_logged；mood 有給 → mood_logged
        // idempotency 同 user 同 logged_on 同事件一天只 publish 一次
        if (! empty($data['tags'])) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::SYMPTOM_LOGGED,
                ['symptom_id' => $symptom->id, 'tags' => $symptom->tags, 'logged_on' => $symptom->logged_on->toDateString()],
                IdempotencyKey::make(CalendarEventCatalog::SYMPTOM_LOGGED, $user->id, $symptom->id, $symptom->logged_on->toDateString()),
            );
        }
        if (! empty($data['mood'])) {
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::MOOD_LOGGED,
                ['symptom_id' => $symptom->id, 'mood' => $symptom->mood, 'logged_on' => $symptom->logged_on->toDateString()],
                IdempotencyKey::make(CalendarEventCatalog::MOOD_LOGGED, $user->id, $symptom->id, $symptom->logged_on->toDateString()),
            );
        }

        // Wave 13 — BodyDex：每個 tag 各記一筆（30 種 catalog 收集）
        $loggedOn = CarbonImmutable::parse($symptom->logged_on->toDateString());
        foreach ((array) $symptom->tags as $tag) {
            try {
                $this->bodyDex->record((int) $user->id, (string) $tag, $loggedOn);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'data' => [
                'id' => $symptom->id,
                'logged_on' => $symptom->logged_on->toDateString(),
                'tags' => $symptom->tags,
                'mood' => $symptom->mood,
            ],
        ], 201);
    }
}
