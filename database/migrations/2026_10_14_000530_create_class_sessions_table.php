<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named class_sessions because Laravel already owns `sessions` (docs/03).
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->string('title');
            $table->dateTime('starts_at'); // local time, Africa/Johannesburg has no DST
            $table->smallInteger('duration_minutes')->unsigned()->default(45);
            $table->smallInteger('capacity')->unsigned();
            $table->smallInteger('min_participants')->unsigned()->nullable();
            $table->unsignedTinyInteger('cutoff_hours')->nullable();
            $table->string('description', 500)->nullable();
            $table->enum('status', ['scheduled', 'cancelled'])->default('scheduled');
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['schedule_template_id', 'starts_at']); // BR-8.2
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
