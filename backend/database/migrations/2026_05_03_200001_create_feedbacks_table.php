<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // enum 用 string 寬鬆儲存（MariaDB 對 enum 改值要 alter table 麻煩）
            $table->string('category', 32);
            $table->text('message');
            $table->string('app_version', 32)->nullable();
            $table->json('device_info')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
