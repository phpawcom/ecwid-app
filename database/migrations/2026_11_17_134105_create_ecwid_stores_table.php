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
        Schema::create('ecwid_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecwid_app_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->string('license_key')->nullable();
            $table->string('license_status', 30)->nullable();
            $table->json('license_gateways')->nullable();
            $table->timestamp('license_checked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['ecwid_app_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecwid_stores');
    }
};
