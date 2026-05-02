<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('health_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);  // healthkit / health_connect / manual
            $table->string('metric', 32);  // basal_temp / sleep_hours / steps / weight_kg / hrv
            $table->decimal('value', 10, 3);
            $table->date('recorded_on');
            $table->timestamp('recorded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'metric', 'recorded_on']);
        });

        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('phase_at_capture', 24)->nullable();
            $table->date('captured_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'captured_on']);
        });

        Schema::create('week_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->json('summary');
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_reports');
        Schema::dropIfExists('progress_photos');
        Schema::dropIfExists('health_samples');
    }
};
