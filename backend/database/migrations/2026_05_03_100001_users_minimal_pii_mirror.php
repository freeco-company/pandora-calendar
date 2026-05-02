<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 ADR-007 §2.3 — calendar `users` 改 minimal mirror。
 *
 * 加：
 *   - display_name (mirror from PC)
 *   - avatar_url (mirror from PC)
 *   - subscription_tier (mirror from PC)
 *   - last_synced_at (reconcile worker 寫)
 *
 * 改：
 *   - email NULLABLE（prod 用 PC JWT 後本地不再存 email；dev demo login 才會填）
 *   - password NULLABLE（同上，本地不存 password_hash）
 *
 * Drop 留待 P1 cutover 一週後 + monitor 確認無寫入再做（避免 sanctum demo
 * login 短期還會用 password 欄位）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url', 500)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('users', 'subscription_tier')) {
                $table->string('subscription_tier', 32)->nullable()->after('avatar_url');
            }
            if (! Schema::hasColumn('users', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('identity_synced_at');
            }
        });

        // email / password / name 改 nullable — 用 doctrine/dbal change() 跨 driver
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['display_name', 'avatar_url', 'subscription_tier', 'last_synced_at'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
