<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('max_guests');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('hotel_room_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->restrictOnDelete();
            $table->date('stay_date');
            $table->unsignedInteger('total_rooms');
            $table->unsignedInteger('available_rooms');
            $table->unsignedBigInteger('nightly_price');
            $table->unique(['room_type_id', 'stay_date']);
        });
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->uuid('request_key')->unique();
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained('hotel_room_types')->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('rooms');
            $table->unsignedSmallInteger('guests');
            $table->unsignedBigInteger('total_amount');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_policy')->default('Hủy trước ngày nhận phòng: hoàn toàn bộ tiền mô phỏng.');
            $table->timestamps();
        });
        Schema::create('hotel_booking_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_id')->constrained('hotel_room_inventory')->restrictOnDelete();
            $table->date('stay_date');
            $table->unsignedBigInteger('nightly_price');
            $table->unsignedSmallInteger('rooms');
            $table->unique(['hotel_booking_id', 'stay_date']);
        });
        Schema::create('hotel_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('completed');
            $table->boolean('is_simulated')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['hotel_payments', 'hotel_booking_nights', 'hotel_bookings', 'hotel_room_inventory', 'hotel_room_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
