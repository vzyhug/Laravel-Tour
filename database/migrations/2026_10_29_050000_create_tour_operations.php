<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->string('operation_status', 20)->default('scheduled');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
        });
        Schema::create('passenger_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->unique()->constrained('booking_passengers', 'passenger_id')->cascadeOnDelete();
            $table->string('status', 20);
            $table->foreignId('checked_by')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('departure_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departure_id')->unique()->constrained('tour_departures', 'departure_id')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('employees', 'employee_id')->nullOnDelete();
            $table->text('summary');
            $table->text('incidents')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departure_reports');
        Schema::dropIfExists('passenger_attendances');
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['operation_status', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
