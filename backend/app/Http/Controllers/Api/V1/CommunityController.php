<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommunityModerationLog;
use App\Models\CommunityPost;
use App\Models\CommunityReply;
use App\Models\CommunityReport;
use App\Services\Community\AnonymousHandle;
use App\Services\Community\CommunityGate;
use App\Services\Community\CommunityModerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityGate $gate,
        private readonly CommunityModerator $moderator,
        private readonly AnonymousHandle $handle,
    ) {}

    /** GET /api/v1/community/posts */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(['question', 'experience', 'tip', 'support'])],
            'sort' => ['nullable', Rule::in(['latest', 'hot', 'mine'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = CommunityPost::query()->published();

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }
        if (($data['sort'] ?? 'latest') === 'mine') {
            $query->where('user_id', $request->user()->id);
        }
        $query = match ($data['sort'] ?? 'latest') {
            'hot' => $query->orderByDesc('like_count')->orderByDesc('reply_count')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };

        $paginator = $query->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(fn (CommunityPost $p) => $this->formatPost($p, $request))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/v1/community/posts/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::where('status', 'published')->findOrFail($id);

        $replies = CommunityReply::where('post_id', $post->id)
            ->whereIn('status', ['published'])
            ->orderBy('is_dodo', 'desc') // dodo sticky
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => array_merge(
                $this->formatPost($post, $request),
                [
                    'replies' => $replies->map(fn (CommunityReply $r) => $this->formatReply($r, $request))->all(),
                ],
            ),
        ]);
    }

    /** POST /api/v1/community/posts */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $gate = $this->gate->canPost($user);
        if (! $gate['ok']) {
            return response()->json([
                'message' => $gate['hint'] ?? 'Not eligible to post yet.',
                'errors' => ['gate' => [$gate['reason']]],
                'gate' => $gate,
            ], 422);
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(['question', 'experience', 'tip', 'support'])],
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $verdict = $this->moderator->evaluate($data['title'], $data['body']);

        return DB::transaction(function () use ($user, $data, $verdict) {
            $post = new CommunityPost([
                'user_id' => $user->id,
                'category' => $data['category'],
                'title' => $data['title'],
                'body' => $data['body'],
                'moderation_score' => $verdict['score'],
                'status' => $verdict['action'] === CommunityModerator::ACTION_BLOCK ? 'removed' : 'published',
                'published_at' => $verdict['action'] === CommunityModerator::ACTION_BLOCK ? null : now(),
                'anonymous_handle' => 'pending', // placeholder, rewritten below once we have id
            ]);
            $post->save();

            // Now we have a stable id — derive handle deterministically.
            $post->anonymous_handle = $this->handle->forPost($user->id, $post->id);
            $post->save();

            CommunityModerationLog::create([
                'target_type' => 'post',
                'target_id' => $post->id,
                'action' => $verdict['action'] === CommunityModerator::ACTION_BLOCK ? 'auto_block'
                    : ($verdict['action'] === CommunityModerator::ACTION_FLAG ? 'auto_flag' : 'approve'),
                'reason' => implode(',', $verdict['reasons']) ?: null,
                'matched_rules' => $verdict['matched'] ?: null,
            ]);

            if ($verdict['action'] === CommunityModerator::ACTION_BLOCK) {
                return response()->json([
                    'message' => $verdict['hint'] ?? '這篇內容暫時無法發布。',
                    'errors' => ['moderation' => $verdict['reasons']],
                    'moderation' => [
                        'reasons' => $verdict['reasons'],
                        'hint' => $verdict['hint'],
                    ],
                ], 422);
            }

            // Self-harm flag → auto dodo reply with hotline
            if ($verdict['needs_dodo_reply']) {
                $reply = CommunityReply::create([
                    'post_id' => $post->id,
                    'user_id' => $user->id, // owner-context only; never exposed
                    'anonymous_handle' => 'dodo-team',
                    'body' => $this->moderator->dodoSelfHarmReply(),
                    'status' => 'published',
                    'is_dodo' => true,
                ]);
                $post->increment('reply_count');
                CommunityModerationLog::create([
                    'target_type' => 'reply',
                    'target_id' => $reply->id,
                    'action' => 'dodo_reply',
                    'reason' => 'self_harm_signal',
                ]);
            }

            return response()->json(['data' => $this->formatPost($post->fresh(), request())], 201);
        });
    }

    /** POST /api/v1/community/posts/{id}/replies */
    public function reply(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $gate = $this->gate->canReply($user);
        if (! $gate['ok']) {
            return response()->json([
                'message' => $gate['hint'] ?? 'Not eligible to reply yet.',
                'errors' => ['gate' => [$gate['reason']]],
                'gate' => $gate,
            ], 422);
        }

        $post = CommunityPost::where('status', 'published')->findOrFail($id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ]);

        $verdict = $this->moderator->evaluate('', $data['body']);

        return DB::transaction(function () use ($user, $post, $data, $verdict) {
            $reply = CommunityReply::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                // Reply handle is derived from parent post id so the OP appears as
                // the same handle as the post itself ("OP" continuity).
                'anonymous_handle' => $this->handle->forPost($user->id, $post->id),
                'body' => $data['body'],
                'moderation_score' => $verdict['score'],
                'status' => $verdict['action'] === CommunityModerator::ACTION_BLOCK ? 'removed' : 'published',
            ]);

            CommunityModerationLog::create([
                'target_type' => 'reply',
                'target_id' => $reply->id,
                'action' => $verdict['action'] === CommunityModerator::ACTION_BLOCK ? 'auto_block'
                    : ($verdict['action'] === CommunityModerator::ACTION_FLAG ? 'auto_flag' : 'approve'),
                'reason' => implode(',', $verdict['reasons']) ?: null,
                'matched_rules' => $verdict['matched'] ?: null,
            ]);

            if ($verdict['action'] === CommunityModerator::ACTION_BLOCK) {
                return response()->json([
                    'message' => $verdict['hint'] ?? '這則回覆暫時無法發布。',
                    'errors' => ['moderation' => $verdict['reasons']],
                    'moderation' => [
                        'reasons' => $verdict['reasons'],
                        'hint' => $verdict['hint'],
                    ],
                ], 422);
            }

            $post->increment('reply_count');

            return response()->json(['data' => $this->formatReply($reply->fresh(), request())], 201);
        });
    }

    /** POST /api/v1/community/posts/{id}/like */
    public function likePost(Request $request, int $id): JsonResponse
    {
        if (! $this->checkRateLimit($request->user()->id, 'like', 30, 60)) {
            return response()->json(['message' => '太快了，請稍後再試。'], 429);
        }

        $post = CommunityPost::where('status', 'published')->findOrFail($id);
        $key = "community:liked:post:{$post->id}:{$request->user()->id}";

        if (Cache::get($key)) {
            Cache::forget($key);
            $post->decrement('like_count');

            return response()->json(['data' => ['liked' => false, 'like_count' => max(0, $post->like_count - 1)]]);
        }
        Cache::put($key, 1, now()->addDays(365));
        $post->increment('like_count');

        return response()->json(['data' => ['liked' => true, 'like_count' => $post->like_count + 1]]);
    }

    /** POST /api/v1/community/replies/{id}/like */
    public function likeReply(Request $request, int $id): JsonResponse
    {
        if (! $this->checkRateLimit($request->user()->id, 'like', 30, 60)) {
            return response()->json(['message' => '太快了，請稍後再試。'], 429);
        }

        $reply = CommunityReply::where('status', 'published')->findOrFail($id);
        $key = "community:liked:reply:{$reply->id}:{$request->user()->id}";

        if (Cache::get($key)) {
            Cache::forget($key);
            $reply->decrement('like_count');

            return response()->json(['data' => ['liked' => false, 'like_count' => max(0, $reply->like_count - 1)]]);
        }
        Cache::put($key, 1, now()->addDays(365));
        $reply->increment('like_count');

        return response()->json(['data' => ['liked' => true, 'like_count' => $reply->like_count + 1]]);
    }

    /** POST /api/v1/community/reports */
    public function report(Request $request): JsonResponse
    {
        $user = $request->user();

        $gate = $this->gate->canReport($user);
        if (! $gate['ok']) {
            return response()->json([
                'message' => $gate['hint'] ?? 'Not eligible to report.',
                'errors' => ['gate' => [$gate['reason']]],
            ], 422);
        }

        $data = $request->validate([
            'target_type' => ['required', Rule::in(['post', 'reply'])],
            'target_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(['spam', 'harassment', 'medical_advice', 'commercial', 'self_harm', 'other'])],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        // 10 reports / day / user — defense against weaponized reporting
        $key = "community:report:rate:{$user->id}:".CarbonImmutable::today()->toDateString();
        $count = (int) Cache::get($key, 0);
        if ($count >= 10) {
            return response()->json([
                'message' => '今天的檢舉次數已經到上限。如果有緊急狀況，請直接聯絡客服。',
            ], 429);
        }

        // Avoid uniqueness violation crash → friendly already-reported response
        $existing = CommunityReport::where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->where('reporter_user_id', $user->id)
            ->first();
        if ($existing) {
            return response()->json(['data' => ['already_reported' => true]]);
        }

        DB::transaction(function () use ($data, $user) {
            CommunityReport::create([
                'target_type' => $data['target_type'],
                'target_id' => $data['target_id'],
                'reporter_user_id' => $user->id,
                'reason' => $data['reason'],
                'message' => $data['message'] ?? null,
            ]);

            if ($data['target_type'] === 'post') {
                CommunityPost::where('id', $data['target_id'])->increment('reported_count');
            } else {
                CommunityReply::where('id', $data['target_id'])->increment('reported_count');
            }
        });

        Cache::put($key, $count + 1, now()->endOfDay());

        return response()->json(['data' => ['reported' => true]]);
    }

    /** DELETE /api/v1/community/posts/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = CommunityPost::findOrFail($id);

        // Authorization — can only delete own post. Compare user_id strictly.
        abort_unless($post->user_id === $request->user()->id, 403);

        $post->update(['status' => 'removed']);
        CommunityModerationLog::create([
            'target_type' => 'post',
            'target_id' => $post->id,
            'action' => 'remove',
            'reason' => 'self_delete',
            'moderator_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ---------- helpers ----------

    private function checkRateLimit(int $userId, string $bucket, int $limit, int $perSeconds): bool
    {
        $key = "community:rl:{$bucket}:{$userId}:".intdiv(time(), $perSeconds);
        $count = (int) Cache::get($key, 0);
        if ($count >= $limit) {
            return false;
        }
        Cache::put($key, $count + 1, $perSeconds);

        return true;
    }

    /** @return array<string, mixed> */
    private function formatPost(CommunityPost $post, Request $request): array
    {
        $likeKey = "community:liked:post:{$post->id}:{$request->user()->id}";

        return [
            'id' => $post->id,
            'category' => $post->category,
            'title' => $post->title,
            'body' => $post->body,
            'anonymous_handle' => $post->anonymous_handle,
            'is_mine' => $post->user_id === $request->user()->id,
            'is_dodo' => $post->anonymous_handle === 'dodo-team',
            'liked' => (bool) Cache::get($likeKey),
            'like_count' => $post->like_count,
            'reply_count' => $post->reply_count,
            'has_self_harm_signal' => $post->moderation_score >= 0.7,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatReply(CommunityReply $reply, Request $request): array
    {
        $likeKey = "community:liked:reply:{$reply->id}:{$request->user()->id}";

        return [
            'id' => $reply->id,
            'post_id' => $reply->post_id,
            'body' => $reply->body,
            'anonymous_handle' => $reply->anonymous_handle,
            'is_mine' => ! $reply->is_dodo && $reply->user_id === $request->user()->id,
            'is_dodo' => (bool) $reply->is_dodo,
            'liked' => (bool) Cache::get($likeKey),
            'like_count' => $reply->like_count,
            'created_at' => $reply->created_at?->toIso8601String(),
        ];
    }
}
