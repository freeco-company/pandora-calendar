<?php

namespace App\Services\PhotoJournal;

use App\Models\PhotoJournalEntry;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Premium-only cloud sync 路徑。
 *
 * 隱私 invariants：
 *   1. **必須**先檢查 FeatureGate::isPremium，非 Premium 一律拒。
 *   2. binary 寫入 disk='photo_journal'（private），不是 public disk。
 *   3. Storage 內容透過 Laravel Crypt 加密（即便外洩 raw object 也讀不出）。
 *   4. cloud_url 為 short-TTL signed URL（10 分鐘），不可以給永久 public URL。
 *   5. 刪除路徑同時清 binary + cloud_url + cloud_object_key。
 */
class PhotoJournalUploader
{
    /** signed URL 有效時間（分鐘）— 短 TTL 是隱私防線 */
    private const SIGNED_URL_TTL_MINUTES = 10;

    /** 單檔上限 5MB（對齊集團 frontend asset 規則）*/
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    public function __construct(
        private readonly FeatureGate $gate,
    ) {}

    /**
     * Premium-only：把 binary 上 cloud + 更新 entry 為 synced。
     * 失敗 throw，呼叫端用 try / catch 給 user 訊息。
     */
    public function uploadCloud(User $user, PhotoJournalEntry $entry, UploadedFile $file): PhotoJournalEntry
    {
        if (! $this->gate->isPremium($user)) {
            throw new \DomainException('premium_required');
        }
        if ($entry->user_id !== $user->id) {
            throw new \DomainException('forbidden');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new BadRequestException('file_too_large');
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            throw new BadRequestException('mime_not_allowed');
        }

        // 同 entry 重複上傳：先清舊 object 避免孤兒
        if ($entry->cloud_object_key) {
            $this->safeDeleteObject($entry->cloud_object_key);
        }

        $disk = $this->disk();
        $key = sprintf(
            'photo-journal/%d/%s.%s.enc',
            $user->id,
            Str::uuid()->toString(),
            $file->getClientOriginalExtension() ?: 'bin',
        );

        // server-side encrypt：即便 storage object 外洩 raw bytes 也 useless
        $encrypted = Crypt::encryptString($file->get());
        $disk->put($key, $encrypted, ['visibility' => 'private']);

        $entry->update([
            'cloud_synced' => true,
            'cloud_object_key' => $key,
            'cloud_url' => $this->buildSignedUrl($key),
        ]);

        return $entry->fresh();
    }

    /**
     * 只清 cloud copy，保留 metadata（user 主動「我不想雲端存照片了」）。
     */
    public function unsyncCloud(PhotoJournalEntry $entry): PhotoJournalEntry
    {
        if ($entry->cloud_object_key) {
            $this->safeDeleteObject($entry->cloud_object_key);
        }
        $entry->update([
            'cloud_synced' => false,
            'cloud_url' => null,
            'cloud_object_key' => null,
        ]);

        return $entry->fresh();
    }

    /**
     * 重簽 signed URL（前端要看大圖時 call，TTL 短所以每次重簽）。
     */
    public function refreshSignedUrl(PhotoJournalEntry $entry): ?string
    {
        if (! $entry->cloud_object_key) {
            return null;
        }
        $url = $this->buildSignedUrl($entry->cloud_object_key);
        $entry->update(['cloud_url' => $url]);

        return $url;
    }

    /**
     * 取得解密後的 binary（呼叫方需自行決定 stream / inline）。
     */
    public function readDecrypted(PhotoJournalEntry $entry): ?string
    {
        if (! $entry->cloud_object_key) {
            return null;
        }
        $disk = $this->disk();
        if (! $disk->exists($entry->cloud_object_key)) {
            return null;
        }

        return Crypt::decryptString($disk->get($entry->cloud_object_key));
    }

    private function disk(): Filesystem
    {
        // disk 名取 'photo_journal'，未設定 fallback 到 local（dev）。
        // prod 應 mapping 到 S3 / Linode Object Storage private bucket。
        $name = config('filesystems.disks.photo_journal') ? 'photo_journal' : 'local';

        return Storage::disk($name);
    }

    private function buildSignedUrl(string $key): string
    {
        // 走 backend route 中介存取，server-side decrypt 後 stream 給 client。
        // 不直接給 cloud presigned URL（避免 raw encrypted bytes 流出無法解密 = 給也沒用，
        // 但更直觀的是讓所有訪問都過我們的 auth + signature 雙重檢查）
        return URL::temporarySignedRoute(
            'photo-journal.cloud-stream',
            now()->addMinutes(self::SIGNED_URL_TTL_MINUTES),
            ['key' => base64_encode($key)],
        );
    }

    private function safeDeleteObject(string $key): void
    {
        try {
            $this->disk()->delete($key);
        } catch (\Throwable $e) {
            Log::warning('photo_journal cloud object delete failed', [
                'key' => $key, 'error' => $e->getMessage(),
            ]);
        }
    }
}
