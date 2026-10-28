<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['images_tour', 'itinerary_place_images'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->string('storage_disk', 50)->nullable();
                $table->string('storage_bucket')->nullable();
                $table->string('storage_path', 1000)->nullable();
                if ($name === 'images_tour') {
                    $table->string('sha256', 64)->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['images_tour', 'itinerary_place_images'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropColumn(['storage_disk', 'storage_bucket', 'storage_path']);
                if ($name === 'images_tour') {
                    $table->dropIndex(['sha256']);
                    $table->dropColumn('sha256');
                }
            });
        }
    }
};
