<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-4.1 (one in effect, one requested) is enforced in the actions, inside a household lock.
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['requested', 'active', 'paused', 'cancelled', 'declined']);
            $table->unsignedSmallInteger('monthly_credits');
            $table->unsignedInteger('monthly_price_cents');
            $table->char('starts_period', 7)->nullable();
            $table->char('ends_period', 7)->nullable();
            // The first period the monthly job may grant for (BR-4.4, 4.9), so a grant is never due just because a row is missing.
            $table->char('grants_from_period', 7)->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('membership_member', function (Blueprint $table) {
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained();

            $table->primary(['membership_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_member');
        Schema::dropIfExists('memberships');
    }
};
