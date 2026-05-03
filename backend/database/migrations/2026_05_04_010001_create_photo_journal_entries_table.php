<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5 進度照 photo journal — metadata only。
 *
 * 隱私三層：
 *   (1) 預設 cloud_synced=false，照片只在 device。
 *   (2) backend 永遠不存 binary，metadata 表只有 phase / cycle_day / tag / 短 note。
 *   (3) Premium 才能 toggle cloud sync；cloud_url 為 signed URL，server-side encrypted。
 *
 * 不做臉部辨識 / 不關聯 face hash — 純記錄載體。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // phase 與 cycle_day 是寫入當下 BodyRhythm 快照（前端傳，後端不重算避免漂移）
            $table->string('phase', 32)->nullable();
            $table->unsignedSmallInteger('cycle_day')->nullable();

            // tag enum：face / body / note（純文字日記也走這張表，binary 為空）
            $table->string('tag', 16);

            // 短文字日記，最長 500（schema 強制 + Form Request 二次驗）
            $table->string('note_text', 500)->nullable();

            // device-side 路徑 reference（前端 Capacitor filesystem URI / IndexedDB key），不是 server path
            $table->string('local_path', 512)->nullable();

            // cloud sync（Premium-only）
            $table->boolean('cloud_synced')->default(false);
            $table->string('cloud_url', 1024)->nullable(); // signed URL，private bucket
            $table->string('cloud_object_key', 512)->nullable(); // 內部 storage key（刪除 / 重簽用）

            // BlurHash placeholder — 給未上 cloud 但 device 端要 thumbnail 顯示時當 fallback
            $table->string('thumb_blurhash', 128)->nullable();

            $table->date('captured_on');
            $table->timestamps();

            $table->index(['user_id', 'captured_on']);
            $table->index(['user_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_journal_entries');
    }
};
