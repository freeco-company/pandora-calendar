<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 13 — Pet bond（取代純線性 pet level）。
 * bond_xp 累積，level 由 service 從 xp 推導；不存 derived field 避免 drift。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_bonds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pet_species', 32);
            $table->unsignedInteger('bond_xp')->default(0);
            $table->unsignedSmallInteger('feed_count_today')->default(0);
            $table->unsignedSmallInteger('pet_head_count_today')->default(0);
            $table->date('counters_reset_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pet_species']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_bonds');
    }
};
