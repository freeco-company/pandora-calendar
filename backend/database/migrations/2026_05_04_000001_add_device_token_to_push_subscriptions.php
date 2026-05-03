<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push channels build-out — 加 device_token 欄位給 iOS / Android native push。
 *
 * 既有 web-push 用 endpoint + p256dh + auth；ios/android 用 device_token。
 * device_token 與 endpoint 互斥（依 platform 二擇一），不強制 schema-level 約束。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('push_subscriptions', 'device_token')) {
                $table->string('device_token', 500)->nullable()->after('platform');
                $table->index('device_token');
            }
        });

        // Web-push migration：原本 endpoint NOT NULL；ios/android 不會有 endpoint，
        // 改 nullable 才能共存。MariaDB / SQLite 都支援。
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('endpoint', 500)->nullable()->change();
            $table->string('p256dh', 255)->nullable()->change();
            $table->string('auth', 128)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('push_subscriptions', 'device_token')) {
                $table->dropIndex(['device_token']);
                $table->dropColumn('device_token');
            }
        });
    }
};
