<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_orders', fn (Blueprint $table) => $table->string('reconciliation_status', 30)->default('clear'));
    }

    public function down(): void
    {
        Schema::table('platform_orders', fn (Blueprint $table) => $table->dropColumn('reconciliation_status'));
    }
};
