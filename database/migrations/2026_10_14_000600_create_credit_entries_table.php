<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ledger (BR-5.1, 5.3): rows are only ever inserted, so there is no updated_at.
        Schema::create('credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->char('period', 7);
            $table->enum('type', ['grant', 'booking', 'refund', 'adjustment', 'expiry']);
            $table->smallInteger('amount');
            $table->foreignId('membership_id')->nullable()->constrained();
            // The bookings table arrives in Milestone 3, which adds the foreign key.
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('description');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['household_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_entries');
    }
};
