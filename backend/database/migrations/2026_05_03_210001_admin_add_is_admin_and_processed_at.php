<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin moderation panel groundwork.
 *
 * - users.is_admin: gate for AdminPanelProvider; defaults false so existing
 *   prod accounts cannot suddenly authenticate into /admin.
 * - feedbacks.processed_at: lets moderators tick "已處理" without losing the
 *   row (PM still wants the long-tail data for clustering).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('subscription_tier');
                $table->index('is_admin');
            }
        });

        Schema::table('feedbacks', function (Blueprint $table): void {
            if (! Schema::hasColumn('feedbacks', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('device_info');
                $table->index('processed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropIndex(['is_admin']);
                $table->dropColumn('is_admin');
            }
        });

        Schema::table('feedbacks', function (Blueprint $table): void {
            if (Schema::hasColumn('feedbacks', 'processed_at')) {
                $table->dropIndex(['processed_at']);
                $table->dropColumn('processed_at');
            }
        });
    }
};
