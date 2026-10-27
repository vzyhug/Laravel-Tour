<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_places', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_url', 1500);
            $table->date('verified_on');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('itinerary_place_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('itinerary_places')->cascadeOnDelete();
            $table->string('path', 1000);
            $table->text('caption');
            $table->string('source_url', 1500);
            $table->text('author');
            $table->string('license', 255);
            $table->string('license_url', 1500)->nullable();
            $table->string('sha256', 64);
            $table->unique(['place_id', 'sha256']);
        });
        Schema::create('tour_itinerary_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('tour_schedules', 'schedule_id')->cascadeOnDelete();
            $table->foreignId('place_id')->constrained('itinerary_places')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unique(['schedule_id', 'position']);
        });
        Schema::create('customer_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['customer_id', 'tour_id']);
        });
        Schema::create('customer_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('customer_itineraries')->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('itinerary_places')->restrictOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->unsignedSmallInteger('position');
            $table->string('title', 160);
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->unique(['itinerary_id', 'day_number', 'position']);
        });
        Schema::create('itinerary_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('itinerary_id')->constrained('customer_itineraries')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->unsignedInteger('itinerary_version');
            $table->json('snapshot');
            $table->date('travel_date');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('contact_name', 100);
            $table->string('contact_phone', 30);
            $table->string('contact_email');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('submitted')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('employee_id')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->unsignedBigInteger('quoted_total')->nullable();
            $table->text('reply')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['itinerary_consultations', 'customer_itinerary_items', 'customer_itineraries', 'tour_itinerary_stops', 'itinerary_place_images', 'itinerary_places'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
