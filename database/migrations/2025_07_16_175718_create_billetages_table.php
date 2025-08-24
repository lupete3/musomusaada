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
        Schema::create('billetages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloture_id')->constrained('clotures')->onDelete('cascade');
            $table->string('currency', 3);
            $table->decimal('denomination', 15, 2);
            $table->integer('quantity');
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billetages');
    }
};
