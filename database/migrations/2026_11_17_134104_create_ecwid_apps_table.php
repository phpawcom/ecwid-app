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
        Schema::create('ecwid_apps', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique()->comment('Ecwid app namespace / client_id');
            $table->text('client_secret')->comment('encrypted');
            $table->string('gateway', 50);
            $table->string('name');
            $table->string('legacy_path')->nullable()->unique()->comment('"." for the app that answers on the v1 URLs');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecwid_apps');
    }
};
