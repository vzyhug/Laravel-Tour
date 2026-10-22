<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id('coupon_id');
            $table->string('code', 50)->unique();
            $table->unsignedTinyInteger('discount_percent');
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_order', 15, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->constrained('coupons', 'coupon_id')->nullOnDelete();
            $table->decimal('discount_amount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('coupons');
    }
};
