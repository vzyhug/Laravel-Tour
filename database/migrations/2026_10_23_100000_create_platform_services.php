<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_suppliers')) {
            Schema::create('platform_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_products')) {
            Schema::create('platform_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('platform_suppliers');
                $table->string('type', 20)->index();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('location');
                $table->text('description');
                $table->string('image_path')->nullable();
                $table->json('attributes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_resources')) {
            Schema::create('platform_resources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('platform_products');
                $table->string('name');
                $table->string('kind', 30);
                $table->unsignedInteger('capacity');
                $table->unsignedInteger('buffer_minutes')->default(0);
                $table->json('attributes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_options')) {
            Schema::create('platform_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained('platform_resources');
                $table->string('code')->unique();
                $table->string('name');
                $table->unsignedBigInteger('price');
                $table->unsignedBigInteger('child_price')->nullable();
                $table->unsignedInteger('max_adults')->default(2);
                $table->unsignedInteger('max_children')->default(0);
                $table->unsignedInteger('occupancy')->default(2);
                $table->json('attributes')->nullable();
                $table->json('policy');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_inventory_days')) {
            Schema::create('platform_inventory_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained('platform_resources');
                $table->date('day');
                $table->unsignedInteger('capacity');
                $table->bigInteger('price_adjustment')->default(0);
                $table->boolean('is_closed')->default(false);
                $table->unique(['resource_id', 'day']);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_trips')) {
            Schema::create('platform_trips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained('platform_resources');
                $table->string('code')->unique();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_trip_stops')) {
            Schema::create('platform_trip_stops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained('platform_trips');
                $table->unsignedSmallInteger('position');
                $table->string('name');
                $table->string('address');
                $table->timestamp('time_at');
                $table->unique(['trip_id', 'position']);
            });
        }
        if (! Schema::hasTable('platform_quotes')) {
            Schema::create('platform_quotes', function (Blueprint $table) {
                $table->id();
                $table->uuid('token')->unique();
                $table->foreignId('customer_id')->constrained('customers', 'customer_id');
                $table->foreignId('option_id')->constrained('platform_options');
                $table->text('configuration');
                $table->json('snapshot');
                $table->char('fingerprint', 64);
                $table->unsignedBigInteger('total');
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_orders')) {
            Schema::create('platform_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('customer_id')->constrained('customers', 'customer_id');
                $table->foreignId('option_id')->constrained('platform_options');
                $table->foreignId('quote_id')->unique()->constrained('platform_quotes');
                $table->uuid('checkout_key');
                $table->char('request_hash', 64);
                $table->string('type', 20)->index();
                $table->text('configuration');
                $table->json('snapshot');
                $table->text('contact');
                $table->text('travelers');
                $table->unsignedBigInteger('total');
                $table->string('currency', 3)->default('VND');
                $table->string('reservation_status', 30)->default('held');
                $table->string('payment_status', 30)->default('unpaid');
                $table->string('fulfillment_status', 30)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('service_starts_at');
                $table->timestamp('service_ends_at');
                $table->timestamps();
                $table->unique(['customer_id', 'checkout_key']);
                $table->index(['reservation_status', 'expires_at']);
            });
        }
        if (! Schema::hasTable('platform_allocations')) {
            Schema::create('platform_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('platform_orders');
                $table->foreignId('resource_id')->constrained('platform_resources');
                $table->foreignId('trip_id')->nullable()->constrained('platform_trips');
                $table->string('seat', 10)->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->unsignedInteger('quantity');
                $table->timestamp('released_at')->nullable();
                $table->index(['resource_id', 'released_at', 'starts_at', 'ends_at'], 'platform_alloc_window');
            });
        }
        if (! Schema::hasTable('platform_payments')) {
            Schema::create('platform_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('platform_orders');
                $table->uuid('attempt_key')->unique();
                $table->string('reference', 60)->unique();
                $table->string('provider')->default('demo');
                $table->string('method', 30);
                $table->char('request_hash', 64);
                $table->string('status', 30);
                $table->unsignedBigInteger('amount');
                $table->string('currency', 3)->default('VND');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_payment_events')) {
            Schema::create('platform_payment_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('payment_reference', 60);
                $table->char('payload_digest', 64);
                $table->string('status', 30);
                $table->timestamp('occurred_at');
                $table->timestamp('received_at');
            });
        }
        if (! Schema::hasTable('platform_refunds')) {
            Schema::create('platform_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained('platform_orders');
                $table->string('reason', 1000);
                $table->unsignedBigInteger('amount');
                $table->string('cancellation_status', 30)->default('requested');
                $table->string('refund_status', 30)->default('requested');
                $table->string('decision_note', 1000)->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_money_transactions')) {
            Schema::create('platform_money_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('platform_orders');
                $table->string('operation_key')->unique();
                $table->string('type', 20);
                $table->unsignedBigInteger('amount');
                $table->string('currency', 3)->default('VND');
                $table->timestamp('occurred_at');
            });
        }
        if (! Schema::hasTable('platform_vouchers')) {
            Schema::create('platform_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained('platform_orders');
                $table->uuid('code')->unique();
                $table->unsignedInteger('remaining_uses')->default(1);
                $table->string('status', 20)->default('issued');
                $table->timestamp('valid_from');
                $table->timestamp('valid_until');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('platform_events')) {
            Schema::create('platform_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('platform_orders');
                $table->string('type', 60);
                $table->string('operation_key')->nullable()->unique();
                $table->json('details')->nullable();
                $table->timestamp('occurred_at');
            });
        }
        if (! Schema::hasTable('platform_inspections')) {
            Schema::create('platform_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('platform_orders');
                $table->string('phase', 20);
                $table->unsignedBigInteger('odometer');
                $table->unsignedSmallInteger('fuel_percent');
                $table->string('condition_note', 1000);
                $table->unsignedBigInteger('deposit_amount')->default(0);
                $table->timestamp('occurred_at');
                $table->unique(['order_id', 'phase']);
            });
        }
        if (! Schema::hasIndex('platform_allocations', 'platform_alloc_window')) {
            Schema::table('platform_allocations', fn (Blueprint $table) => $table->index(['resource_id', 'released_at', 'starts_at', 'ends_at'], 'platform_alloc_window'));
        }
    }

    public function down(): void
    {
        foreach (['inspections', 'events', 'vouchers', 'money_transactions', 'refunds', 'payment_events', 'payments', 'allocations', 'orders', 'quotes', 'trip_stops', 'trips', 'inventory_days', 'options', 'resources', 'products', 'suppliers'] as $table) {
            Schema::dropIfExists('platform_'.$table);
        }
    }
};
