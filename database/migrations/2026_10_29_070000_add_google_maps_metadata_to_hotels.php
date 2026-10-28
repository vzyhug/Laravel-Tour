<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('google_place_id', 255)->nullable()->unique();
            $table->string('google_data_id', 255)->nullable();
            $table->decimal('google_rating', 3, 1)->nullable();
            $table->unsignedInteger('google_reviews')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 1500)->nullable();
            $table->date('google_verified_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropUnique(['google_place_id']);
            $table->dropColumn([
                'google_place_id',
                'google_data_id',
                'google_rating',
                'google_reviews',
                'latitude',
                'longitude',
                'google_maps_url',
                'google_verified_on',
            ]);
        });
    }
};
