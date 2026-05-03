<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0+P1 補強：
 *   - users.preferences JSON （onboarding state / cycle_length / goal）
 *   - daily_insights 表（衛教文章 by phase × day_offset，narrative agent seed）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferences')) {
                // ADR-007 §2.3：preferences 只放 app-local state（onboarding / goal），不放 PII
                $table->json('preferences')->nullable()->after('outfit_state');
            }
        });

        if (! Schema::hasTable('daily_insights')) {
            Schema::create('daily_insights', function (Blueprint $table) {
                $table->id();
                $table->string('phase', 16);            // menstrual / follicular / ovulation / luteal
                $table->unsignedSmallInteger('day_offset'); // phase 內第幾天（0-base）
                $table->string('title', 120);
                $table->text('body');                   // 衛教正文（已過 sanitizer）
                $table->string('cta_label', 60)->nullable();
                $table->string('cta_route', 80)->nullable();
                $table->string('source', 60)->nullable(); // 來源 / 編輯署名
                $table->timestamps();
                $table->unique(['phase', 'day_offset']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_insights');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferences')) {
                $table->dropColumn('preferences');
            }
        });
    }
};
