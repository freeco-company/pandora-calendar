<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PhotoJournalEntry;
use App\Services\PhotoJournal\PhotoJournalUploader;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Photo journal — metadata first，binary 永遠不必經過 store endpoint。
 *
 * 端點：
 *   POST   /v1/photo-journal                   metadata 寫入（free，binary 留 device）
 *   GET    /v1/photo-journal/list?month=YYYY-MM  該月清單
 *   GET    /v1/photo-journal/{id}              單筆 detail（含重簽 signed URL）
 *   POST   /v1/photo-journal/{id}/upload-cloud Premium-only，binary multipart upload
 *   DELETE /v1/photo-journal/{id}              全清（metadata + cloud copy）
 *   DELETE /v1/photo-journal/{id}/cloud-only   只清 cloud，保留 metadata
 */
class PhotoJournalController extends Controller
{
    public function __construct(
        private readonly PhotoJournalUploader $uploader,
        private readonly FeatureGate $gate,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tag' => ['required', 'in:face,body,note'],
            'captured_on' => ['required', 'date_format:Y-m-d'],
            'cycle_day' => ['nullable', 'integer', 'min:1', 'max:60'],
            'phase' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
            // local_path 純前端 reference（Capacitor filesystem URI / IndexedDB key），
            // backend 不解析、不檢查存在 — 純客戶端標記。
            'local_path' => ['nullable', 'string', 'max:512'],
            'thumb_blurhash' => ['nullable', 'string', 'max:128'],
        ]);

        $entry = PhotoJournalEntry::create([
            'user_id' => $request->user()->id,
            'tag' => $data['tag'],
            'captured_on' => $data['captured_on'],
            'cycle_day' => $data['cycle_day'] ?? null,
            'phase' => $data['phase'] ?? null,
            'note_text' => $data['note'] ?? null,
            'local_path' => $data['local_path'] ?? null,
            'thumb_blurhash' => $data['thumb_blurhash'] ?? null,
            'cloud_synced' => false,
        ]);

        return response()->json(['data' => $this->present($entry)], Response::HTTP_CREATED);
    }

    public function list(Request $request): JsonResponse
    {
        $month = $request->query('month'); // YYYY-MM
        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $month = CarbonImmutable::now()->format('Y-m');
        }
        $start = CarbonImmutable::parse("{$month}-01");
        $end = $start->endOfMonth();

        $entries = PhotoJournalEntry::where('user_id', $request->user()->id)
            ->whereBetween('captured_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('captured_on')
            ->get();

        return response()->json([
            'data' => [
                'month' => $month,
                'count' => $entries->count(),
                'entries' => $entries->map(fn ($e) => $this->present($e))->all(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $entry = $this->findOwned($request, $id);

        // 短 TTL signed URL → 每次 detail 重簽
        if ($entry->cloud_synced && $entry->cloud_object_key) {
            $this->uploader->refreshSignedUrl($entry);
            $entry->refresh();
        }

        return response()->json(['data' => $this->present($entry)]);
    }

    public function uploadCloud(Request $request, int $id): JsonResponse
    {
        $entry = $this->findOwned($request, $id);

        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '雲端同步是 Premium 功能。妳的照片仍可繼續安全地存在裝置上 ♥',
                'paywall_redirect' => '/me/premium',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:5120'], // 5 MB
        ]);

        try {
            $entry = $this->uploader->uploadCloud(
                $request->user(),
                $entry,
                $request->file('photo'),
            );
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        return response()->json(['data' => $this->present($entry)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = $this->findOwned($request, $id);

        // 全清：先 unsync cloud（清 binary）→ 再刪 metadata
        $this->uploader->unsyncCloud($entry);
        $entry->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function destroyCloudOnly(Request $request, int $id): JsonResponse
    {
        $entry = $this->findOwned($request, $id);
        $entry = $this->uploader->unsyncCloud($entry);

        return response()->json(['data' => $this->present($entry)]);
    }

    /**
     * Stream cloud object：經由 signed URL 抵達，server-side 解密後 inline。
     * Route name = photo-journal.cloud-stream，必須過 hasValidSignature。
     */
    public function cloudStream(Request $request, string $key)
    {
        abort_unless($request->hasValidSignature(), 403, 'invalid or expired link');

        $objectKey = base64_decode($key, true);
        abort_if($objectKey === false, 400, 'bad key');

        $entry = PhotoJournalEntry::where('cloud_object_key', $objectKey)->first();
        abort_unless($entry, 404);

        // 二次邊界檢查：登入 user 必須是 entry owner（signed URL 已包 expires；多一層防 share 連結）
        abort_unless($request->user() && $request->user()->id === $entry->user_id, 403);

        $bytes = $this->uploader->readDecrypted($entry);
        abort_unless($bytes !== null, 404);

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=60, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function findOwned(Request $request, int $id): PhotoJournalEntry
    {
        $entry = PhotoJournalEntry::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($entry, 404);

        return $entry;
    }

    private function present(PhotoJournalEntry $e): array
    {
        return [
            'id' => $e->id,
            'tag' => $e->tag,
            'captured_on' => $e->captured_on?->format('Y-m-d'),
            'cycle_day' => $e->cycle_day,
            'phase' => $e->phase,
            'note' => $e->note_text,
            'local_path' => $e->local_path,
            'thumb_blurhash' => $e->thumb_blurhash,
            'cloud_synced' => $e->cloud_synced,
            'cloud_url' => $e->cloud_url,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
