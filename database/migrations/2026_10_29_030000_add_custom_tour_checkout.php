<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->index();
        });

        Schema::table('itinerary_consultations', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('consultation_id')->nullable()->unique()->constrained('itinerary_consultations')->restrictOnDelete();
            $table->json('quote_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['consultation_id']);
            $table->dropUnique(['consultation_id']);
            $table->dropColumn(['consultation_id', 'quote_snapshot']);
        });

        Schema::table('itinerary_consultations', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'rejected_at']);
        });

        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropIndex(['is_private']);
            $table->dropColumn('is_private');
        });
    }
};
