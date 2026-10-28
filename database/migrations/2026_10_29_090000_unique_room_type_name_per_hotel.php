<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_room_types', function (Blueprint $table) {
            $table->unique(['hotel_id', 'name'], 'hotel_room_types_hotel_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_room_types', function (Blueprint $table) {
            $table->dropUnique('hotel_room_types_hotel_name_unique');
        });
    }
};
