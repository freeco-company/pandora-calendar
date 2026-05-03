<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — BodyDex（身體圖鑑 30 種 symptom_key）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_dex_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('symptom_key', 64);
            $table->date('first_logged_on');
            $table->unsignedInteger('log_count')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'symptom_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_dex_entries');
    }
};
