<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No soft deletes: a package is deleted only if no membership ever used it, otherwise archived (BR-3.4).
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('sessions_per_week');
            $table->unsignedSmallInteger('credits_per_member');
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // BR-3.2: the price for N members is the tier with the largest member_count <= N.
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('member_count');
            $table->unsignedInteger('price_cents');
            $table->timestamps();

            $table->unique(['package_id', 'member_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('packages');
    }
};
