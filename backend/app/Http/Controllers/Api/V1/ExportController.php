<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\UserDataExporter;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ExportController extends Controller
{
    public function __construct(
        private readonly UserDataExporter $exporter,
        private readonly FeatureGate $gate,
    ) {}

    public function pdf(Request $request): JsonResponse
    {
        if ($resp = $this->premiumGuard($request)) {
            return $resp;
        }
        $data = $this->validateRange($request);

        $result = $this->exporter->exportToPdf(
            $request->user()->id,
            $data['from'],
            $data['to'],
        );

        return response()->json([
            'data' => $this->buildDownloadPayload($result),
        ]);
    }

    public function csv(Request $request): JsonResponse
    {
        if ($resp = $this->premiumGuard($request)) {
            return $resp;
        }
        $data = $this->validateRange($request);

        $result = $this->exporter->exportToCsv(
            $request->user()->id,
            $data['from'],
            $data['to'],
        );

        return response()->json([
            'data' => $this->buildDownloadPayload($result),
        ]);
    }

    /**
     * Signed download — 比對 user_id 後送檔；signed URL 有 expires_at 自帶 signature。
     */
    public function download(Request $request, int $userId, string $filename)
    {
        // 嚴守租戶邊界：路徑 user_id 必須等於登入 user
        abort_unless($request->user()->id === $userId, 403);
        abort_unless($request->hasValidSignature(), 403, 'invalid or expired link');

        $path = "exports/{$userId}/{$filename}";
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $filename);
    }

    private function premiumGuard(Request $request): ?JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '匯出資料是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        return null;
    }

    /**
     * @return array{from: ?CarbonImmutable, to: ?CarbonImmutable}
     */
    private function validateRange(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'from' => isset($data['from']) ? CarbonImmutable::parse($data['from']) : null,
            'to' => isset($data['to']) ? CarbonImmutable::parse($data['to']) : null,
        ];
    }

    /**
     * @param  array{path: string, disk: string, filename: string}  $result
     * @return array{download_url: string, expires_at: string, filename: string}
     */
    private function buildDownloadPayload(array $result): array
    {
        $expires = now()->addHour();
        $url = URL::temporarySignedRoute(
            'export.download',
            $expires,
            ['userId' => auth()->id(), 'filename' => $result['filename']],
        );

        return [
            'download_url' => $url,
            'expires_at' => $expires->toAtomString(),
            'filename' => $result['filename'],
        ];
    }
}
