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
        Schema::create('event_store', function (Blueprint $table) {
            $table->id();

            // Aggregate identity
            $table->string('aggregate_uuid');
            $table->string('aggregate_type');

            // Event data
            $table->string('event_type');
            $table->json('event_data');

            // Versioning (VERY important)
            $table->unsignedBigInteger('version');

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['aggregate_uuid', 'aggregate_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};
