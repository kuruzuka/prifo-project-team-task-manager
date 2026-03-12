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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable'); // Creates loggable_type and loggable_id
            $table->string('activity_type');
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
