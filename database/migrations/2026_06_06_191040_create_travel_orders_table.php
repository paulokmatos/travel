<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('travel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('status')->default('solicitado');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['departure_date', 'return_date']);
            $table->index('destination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_orders');
    }
};
