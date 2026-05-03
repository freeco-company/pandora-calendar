<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 pregnancy mode — extend pregnancies table with explicit status / reason / mode_started_at
 *
 * Why additive:
 *   - existing columns (lmp_date / estimated_due_date / ended_on / outcome / milestones) stay untouched
 *   - new `status` enum (active / paused / ended) makes "is mode currently on" trivially queryable
 *     (was previously inferred from `ended_on IS NULL` — fine, but we want paused as a 1st-class state)
 *   - `ended_reason` is the user-facing reason (miscarriage / birth / cancelled / false_alarm); maps loosely
 *     to `outcome` but with cancelled / false_alarm as new options (sensitive UX)
 *   - `mode_started_at` separates "when did user enter the mode" from `created_at` (record creation)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pregnancies', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('outcome');
            $table->string('ended_reason', 24)->nullable()->after('status');
            $table->timestamp('mode_started_at')->nullable()->after('ended_reason');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pregnancies', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['status', 'ended_reason', 'mode_started_at']);
        });
    }
};
