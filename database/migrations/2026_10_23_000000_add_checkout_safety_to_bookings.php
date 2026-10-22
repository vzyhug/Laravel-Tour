<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('checkout_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->json('service_snapshot')->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unique(['customer_id', 'checkout_key']);
            $table->index(['status', 'payment_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'checkout_key']);
            $table->dropIndex(['status', 'payment_expires_at']);
            $table->dropColumn(['checkout_key', 'request_hash', 'service_snapshot', 'payment_expires_at', 'expired_at']);
        });
    }
};
