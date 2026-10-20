<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id('favorite_id');
            $table->foreignId('customer_id')->constrained('customers', 'customer_id')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours', 'tour_id')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
