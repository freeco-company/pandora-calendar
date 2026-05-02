<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 鏡像母艦 customer 關聯（display-only fields，照 ADR-007 §2.3 不存 PII）。
 *
 * - mother_customer_id: 母艦 user id（不是 email / phone）
 * - mother_total_orders: 數量 only（不存訂單細節）
 * - mother_first_order_at / mother_last_order_at: timestamps for lifecycle eval
 *
 * 來源：透過 Pandora Core webhook 由母艦同步進來。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('mother_customer_id')->nullable()->after('identity_synced_at');
            $table->unsignedInteger('mother_total_orders')->default(0)->after('mother_customer_id');
            $table->timestamp('mother_first_order_at')->nullable()->after('mother_total_orders');
            $table->timestamp('mother_last_order_at')->nullable()->after('mother_first_order_at');

            $table->index('mother_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['mother_customer_id']);
            $table->dropColumn(['mother_customer_id', 'mother_total_orders', 'mother_first_order_at', 'mother_last_order_at']);
        });
    }
};
