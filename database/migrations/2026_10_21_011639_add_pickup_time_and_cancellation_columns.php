<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->time('departure_time')->nullable()->after('departure_date');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('pickup_type', ['self', 'door'])->default('self');
            $table->string('pickup_address')->nullable();
            $table->string('pickup_note')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedTinyInteger('refund_percent')->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_type', 'pickup_address', 'pickup_note',
                'cancelled_at', 'cancel_reason', 'refund_percent', 'refund_amount',
            ]);
        });

        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropColumn('departure_time');
        });
    }
};
