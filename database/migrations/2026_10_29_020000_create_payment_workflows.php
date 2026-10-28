<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', fn (Blueprint $table) => $table->unique('booking_id'));
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments', 'payment_id')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('status')->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('provider')->default('mock');
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments', 'payment_id')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status')->default('requested');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('mock_gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('kind');
            $table->string('idempotency_key')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments', 'payment_id')->restrictOnDelete();
            $table->string('status');
            $table->text('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payment_reconciliations', 'mock_gateway_transactions', 'payment_refunds', 'payment_attempts'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('bill', fn (Blueprint $table) => $table->dropUnique(['booking_id']));
    }
};
