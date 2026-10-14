<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BR-9.1: a single row.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('studio_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('admin_notification_emails')->nullable();
            $table->unsignedTinyInteger('default_cutoff_hours')->default(8);
            $table->unsignedSmallInteger('booking_horizon_days')->default(14);
            $table->string('terms_version');
            $table->text('payment_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
