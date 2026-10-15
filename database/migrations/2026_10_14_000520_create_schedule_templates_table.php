<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('location_id')->constrained();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = Monday ... 7 = Sunday
            $table->time('start_time');
            $table->smallInteger('duration_minutes')->unsigned()->default(45);
            $table->smallInteger('capacity')->unsigned();
            $table->smallInteger('min_participants')->unsigned()->nullable();
            $table->unsignedTinyInteger('cutoff_hours')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_templates');
    }
};
