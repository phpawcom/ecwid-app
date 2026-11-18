<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecwid_stores', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->unique()->after('store_email')->comment('secret part of the store\'s webhook URL');
            $table->text('webhook_secret')->nullable()->after('webhook_token')->comment('encrypted; the gateway\'s webhook signing secret');
            $table->timestamp('last_webhook_at')->nullable()->after('webhook_secret');
        });
    }

    public function down(): void
    {
        Schema::table('ecwid_stores', function (Blueprint $table) {
            $table->dropUnique(['webhook_token']);
            $table->dropColumn(['webhook_token', 'webhook_secret', 'last_webhook_at']);
        });
    }
};
