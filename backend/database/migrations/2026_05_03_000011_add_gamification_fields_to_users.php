<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5.3 ADR-009：calendar 端 user 表加 gamification mirror 欄位。
 *
 * 來源：py-service `gamification.level_up` / `outfit_unlocked` /
 * `achievement_awarded` webhook 寫入 — calendar 不自己算 XP，純 mirror。
 *
 * - total_xp / level：lossy mirror（webhook 是 authoritative；不會比 server 高）
 * - outfit_state：JSON {owned:[code...], equipped: code|null}
 * - pet_species / pet_nickname：寵物 species 全集團共用（cat / penguin / hamster / bear）
 *   nickname 由用戶自取，server 不限制
 *
 * 對齊 ADR-007 §2.3 PII 紅線：這些都是 display 欄位、不是 PII。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('total_xp')->default(0)->after('mother_last_order_at');
            $table->unsignedInteger('level')->default(1)->after('total_xp');
            $table->json('outfit_state')->nullable()->after('level');
            $table->string('pet_species', 32)->nullable()->after('outfit_state');
            $table->string('pet_nickname', 64)->nullable()->after('pet_species');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_xp', 'level', 'outfit_state', 'pet_species', 'pet_nickname']);
        });
    }
};
