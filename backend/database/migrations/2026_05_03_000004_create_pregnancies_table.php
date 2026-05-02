<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pregnancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('lmp_date');           // last menstrual period
            $table->date('estimated_due_date'); // 計算 = LMP + 280 天
            $table->date('ended_on')->nullable();
            $table->string('outcome', 16)->nullable(); // delivered / miscarried / terminated / ongoing
            $table->json('milestones')->nullable();    // [{week, note}, ...]
            $table->timestamps();

            $table->index(['user_id', 'ended_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pregnancies');
    }
};
