<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);  // apple / google / ecpay
            $table->string('product_id', 64);  // calendar.premium.monthly / .annual
            $table->string('original_transaction_id')->nullable()->index();
            $table->string('latest_receipt_hash', 64)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->string('status', 16)->default('active'); // active / expired / grace / cancelled / refunded
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32); // initial / renewal / cancel / refund / billing_retry / grace
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
