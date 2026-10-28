<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('momo_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained('payment_attempts')->cascadeOnDelete();
            $table->string('order_id', 200)->unique();
            $table->string('request_id', 50)->unique();
            $table->unsignedBigInteger('amount');
            $table->text('qr_payload')->nullable();
            $table->text('pay_url')->nullable();
            $table->text('deeplink')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('result_code')->nullable();
            $table->string('transaction_id', 100)->nullable()->unique();
            $table->timestamp('expires_at');
            $table->json('gateway_response')->nullable();
            $table->json('ipn_payload')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_payment_sessions');
    }
};
