<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('region', 20);
            $table->text('description');
            $table->string('source_url', 1500);
            $table->date('verified_on');
            $table->timestamps();
        });
        Schema::table('itinerary_places', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->index();
            $table->string('address', 500)->nullable();
        });
        Schema::create('destination_itinerary_place', function (Blueprint $table) {
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('itinerary_place_id')->constrained()->cascadeOnDelete();
            $table->primary(['destination_id', 'itinerary_place_id']);
        });
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('address', 500);
            $table->string('phone', 40)->nullable();
            $table->string('website_url', 1500);
            $table->string('source_url', 1500);
            $table->date('verified_on');
            $table->timestamps();
        });
        Schema::create('hotel_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('path', 1000);
            $table->text('caption');
            $table->string('source_url', 1500);
            $table->text('author');
            $table->string('license', 255);
            $table->string('license_url', 1500)->nullable();
            $table->string('sha256', 64);
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_bucket')->nullable();
            $table->string('storage_path', 1000)->nullable();
            $table->unique(['hotel_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_images');
        Schema::dropIfExists('hotels');
        Schema::table('itinerary_places', function (Blueprint $table) {
            $table->dropColumn(['category', 'address']);
        });
        Schema::dropIfExists('destination_itinerary_place');
        Schema::dropIfExists('destinations');
    }
};
