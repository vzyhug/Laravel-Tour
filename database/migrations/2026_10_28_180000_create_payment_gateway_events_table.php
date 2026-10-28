<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('payment_intent_id')->constrained('payment_intents')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('notification_type', 30);
            $table->string('transaction_id', 100);
            $table->string('outcome', 30);
            $table->string('reason')->nullable();
            $table->json('details');
            $table->timestamps();
            $table->unique(['provider', 'notification_type', 'transaction_id'], 'gateway_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');
    }
};
