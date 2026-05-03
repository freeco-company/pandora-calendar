<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-P2 product polish — pet onboarding, BBT, partner, push subscriptions。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pet_onboarded_at')) {
                $table->timestamp('pet_onboarded_at')->nullable()->after('pet_nickname');
            }
            if (! Schema::hasColumn('users', 'partner_share_token')) {
                $table->string('partner_share_token', 48)->nullable()->unique()->after('pet_onboarded_at');
            }
            if (! Schema::hasColumn('users', 'partner_share_enabled_at')) {
                $table->timestamp('partner_share_enabled_at')->nullable()->after('partner_share_token');
            }
            if (! Schema::hasColumn('users', 'push_opted_in')) {
                $table->boolean('push_opted_in')->default(false)->after('partner_share_enabled_at');
            }
        });

        // BBT (basal body temperature) — 0.01°C 精度，每日 1 筆
        Schema::create('bbt_readings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('measured_on');
            $table->decimal('temperature_c', 4, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'measured_on']);
            $table->index('measured_on');
        });

        // Web Push subscription（PushSubscription JSON：endpoint + p256dh + auth keys）
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh', 255);
            $table->string('auth', 128);
            $table->string('platform', 16)->default('web'); // web / ios / android
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('bbt_readings');
        Schema::table('users', function (Blueprint $table) {
            foreach (['pet_onboarded_at', 'partner_share_token', 'partner_share_enabled_at', 'push_opted_in'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
