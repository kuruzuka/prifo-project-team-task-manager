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
        Schema::create('task_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete();
            $table->string('activity_type');
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_activity_log');
    }
};
