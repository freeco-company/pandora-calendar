<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cycle_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->json('tags');
            $table->string('mood', 16)->nullable();
            $table->decimal('basal_temperature', 4, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'logged_on']);
            $table->unique(['user_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_symptoms');
    }
};
