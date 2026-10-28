<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('booking_id')->constrained('bookings', 'booking_id')->cascadeOnDelete();
            $table->string('reference', 25)->unique();
            $table->string('provider', 30)->default('vietqr_demo');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('status', 20)->default('pending');
            $table->text('qr_payload');
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('provider_transaction_id', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['booking_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
