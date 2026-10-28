<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vnpay_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained('payment_attempts')->cascadeOnDelete();
            $table->string('txn_ref', 100)->unique();
            $table->unsignedBigInteger('amount_vnd');
            $table->unsignedBigInteger('vnp_amount');
            $table->text('payment_url');
            $table->string('status', 20)->default('pending');
            $table->string('response_code', 10)->nullable();
            $table->string('transaction_no', 100)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vnpay_payment_sessions');
    }
};
