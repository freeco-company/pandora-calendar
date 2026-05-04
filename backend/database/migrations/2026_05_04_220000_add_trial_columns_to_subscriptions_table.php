<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freemium funnel — 7-day Premium trial 欄位。
 *
 * 設計：
 *   - 一個 user 一輩子只能用一次 trial（trial_used）
 *   - trial 不需要信用卡、不能購買、不能延長
 *   - 期間 FeatureGate::isPremium() = true（與訂閱效力相同）
 *   - 把欄位加在 users table 而不是 subscriptions table，因為：
 *       1. trial 本質不是 subscription record（沒有 platform / receipt）
 *       2. user 1:1 trial state 比 user N:M subscription 簡單
 *       3. 不會跟 IAP webhook 相互干擾
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'trial_started_at')) {
                $table->timestamp('trial_started_at')->nullable()->after('subscription_tier');
            }
            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            }
            if (! Schema::hasColumn('users', 'trial_used')) {
                $table->boolean('trial_used')->default(false)->after('trial_ends_at');
            }
            if (! Schema::hasColumn('users', 'trial_source')) {
                // onboarding / manual / gift（未來 admin 補發）
                $table->string('trial_source', 16)->nullable()->after('trial_used');
            }

            // index for "find users currently in trial"（後台 / cron 用得上）
            $table->index('trial_ends_at', 'users_trial_ends_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_trial_ends_at_idx');
            $table->dropColumn(['trial_started_at', 'trial_ends_at', 'trial_used', 'trial_source']);
        });
    }
};
