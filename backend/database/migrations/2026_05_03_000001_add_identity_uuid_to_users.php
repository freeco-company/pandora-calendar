<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-007 §2.3：消費端 App 本地只存 uuid + display 欄位，禁止存 PII。
 * email / phone / address / password_hash 一律走 Pandora Core API 取得。
 *
 * 這個 migration 加上：
 * - identity_uuid (集團統一 ID)
 * - identity_synced_at (last sync timestamp)
 *
 * 並把 email / password 變 nullable（demo Phase 0 仍用 email + password；P1+ 改走 IdentityClient）
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('identity_uuid')->nullable()->after('id')->unique();
            $table->timestamp('identity_synced_at')->nullable()->after('identity_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identity_uuid', 'identity_synced_at']);
        });
    }
};
