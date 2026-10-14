<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The household row is the lock target for every credit and booking operation (BR-7.6).
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('name');
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
