<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — Solar term participation（24 節氣，每 3 天一個 window）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_term_participations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('term_key', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('earned_coins')->default(0);
            $table->timestamp('completed_at')->useCurrent();

            $table->unique(['user_id', 'term_key', 'year']);
            $table->index('term_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_term_participations');
    }
};
