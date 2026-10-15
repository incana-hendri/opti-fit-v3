<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions');
            $table->foreignId('member_id')->constrained();
            $table->foreignId('household_id')->constrained(); // denormalised for scoping and locking
            $table->enum('status', ['booked', 'cancelled', 'attended', 'no_show']);
            $table->boolean('credit_charged')->default(true); // BR-6.3
            $table->boolean('late_cancellation')->default(false); // BR-7.7
            $table->foreignId('booked_by')->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            // NULL for cancelled rows, so a member can rebook after cancelling but never hold two active bookings (BR-7.5).
            $table->unsignedTinyInteger('active_key')->nullable()
                ->storedAs("IF(status IN ('booked','attended','no_show'), 1, NULL)");
            $table->timestamps();

            $table->unique(['class_session_id', 'member_id', 'active_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
