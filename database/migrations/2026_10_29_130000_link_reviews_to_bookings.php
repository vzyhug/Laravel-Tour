<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review', function (Blueprint $table) {
            $table->index('customer_id', 'review_customer_lookup');
            $table->dropUnique(['customer_id', 'tour_id']);
            $table->foreignId('booking_id')->nullable()->unique()->constrained('bookings', 'booking_id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};
