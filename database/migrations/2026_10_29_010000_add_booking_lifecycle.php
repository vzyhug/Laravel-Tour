<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Existing bookings retain their previous terms: no automatic expiry.
            $table->timestamp('hold_expires_at')->nullable()->index();
        });
        Schema::create('booking_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings', 'booking_id')->restrictOnDelete();
            $table->foreignId('from_departure_id')->constrained('tour_departures', 'departure_id')->restrictOnDelete();
            $table->foreignId('to_departure_id')->constrained('tour_departures', 'departure_id')->restrictOnDelete();
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_changes');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['hold_expires_at']);
            $table->dropColumn('hold_expires_at');
        });
    }
};
