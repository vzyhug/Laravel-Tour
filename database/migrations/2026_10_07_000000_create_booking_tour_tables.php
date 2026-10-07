<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_name', 50)->unique();
            $table->text('description')->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id('employee_id');
            $table->string('username', 50)->unique();
            $table->string('password_hash');
            $table->string('full_name', 100);
            $table->string('email', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->foreignId('role_id')->constrained('roles', 'role_id');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id('customer_id');
            $table->string('email', 100)->unique();
            $table->string('password_hash');
            $table->string('full_name', 100);
            $table->string('phone', 20);
            $table->string('address')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('tour_categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_name', 50)->unique();
            $table->text('description')->nullable();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id('province_id');
            $table->string('province_name', 50)->unique();
            $table->enum('region', ['Bắc', 'Trung', 'Nam']);
        });

        Schema::create('tours', function (Blueprint $table) {
            $table->id('tour_id');
            $table->string('title', 200);
            $table->foreignId('category_id')->constrained('tour_categories', 'category_id');
            $table->foreignId('province_id')->constrained('provinces', 'province_id');
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('duration_nights')->default(0);
            $table->string('pickup_point')->nullable();
            $table->text('description')->nullable();
            $table->text('itinerary_summary')->nullable();
            $table->text('included_services')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('min_price', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->index(['is_active', 'province_id']);
        });

        Schema::create('images_tour', function (Blueprint $table) {
            $table->id('image_id');
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->string('img_url', 1000);
            $table->boolean('is_thumbnail')->default(false);
        });

        Schema::create('tour_schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title', 200)->nullable();
            $table->text('activities')->nullable();
            $table->boolean('has_breakfast')->default(true);
            $table->boolean('has_lunch')->default(true);
            $table->boolean('has_dinner')->default(true);
            $table->unique(['tour_id', 'day_number']);
        });

        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id('departure_id');
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedInteger('max_seats');
            $table->unsignedInteger('available_seats');
            $table->decimal('adult_price', 15, 2);
            $table->decimal('child_price', 15, 2)->default(0);
            $table->foreignId('guide_id')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->enum('status', ['open', 'full', 'cancelled'])->default('open');
            $table->index(['status', 'departure_date']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id('booking_id');
            $table->string('booking_code', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers', 'customer_id');
            $table->foreignId('departure_id')->constrained('tour_departures', 'departure_id');
            $table->timestamp('booking_date')->useCurrent();
            $table->unsignedInteger('total_adults')->default(1);
            $table->unsignedInteger('total_children')->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->foreignId('created_by_employee')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->index(['customer_id', 'booking_date']);
        });

        Schema::create('booking_passengers', function (Blueprint $table) {
            $table->id('passenger_id');
            $table->foreignId('booking_id')->constrained('bookings', 'booking_id')->cascadeOnDelete();
            $table->string('full_name', 100);
            $table->enum('passenger_type', ['adult', 'child']);
            $table->string('id_card', 20)->nullable();
            $table->date('date_of_birth')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('booking_id')->unique()->constrained('bookings', 'booking_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50);
            $table->timestamp('payment_date')->useCurrent();
            $table->string('transaction_code', 100)->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
        });

        Schema::create('bill', function (Blueprint $table) {
            $table->id('bill_id');
            $table->string('bill_number', 50)->unique();
            $table->foreignId('booking_id')->constrained('bookings', 'booking_id');
            $table->foreignId('employee_id')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->timestamp('bill_date')->useCurrent();
            $table->string('tax_code', 20)->nullable();
            $table->decimal('final_amount', 15, 2);
        });

        Schema::create('review', function (Blueprint $table) {
            $table->id('review_id');
            $table->foreignId('customer_id')->constrained('customers', 'customer_id');
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->text('feedback');
            $table->unsignedTinyInteger('rating');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        foreach (['review', 'bill', 'payments', 'booking_passengers', 'bookings', 'tour_departures', 'tour_schedules', 'images_tour', 'tours', 'provinces', 'tour_categories', 'customers', 'employees', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
