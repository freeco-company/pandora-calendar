<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dodo_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->string('mood', 16);
            $table->string('phase_at_checkin', 24)->nullable();
            $table->unsignedSmallInteger('cycle_day_at_checkin')->nullable();
            $table->text('dodo_response');
            $table->timestamps();

            $table->index(['user_id', 'checked_on']);
            $table->unique(['user_id', 'checked_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dodo_checkins');
    }
};
