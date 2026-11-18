<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecwid_stores', function (Blueprint $table) {
            $table->string('store_name')->nullable()->after('store_id');
            $table->string('store_url')->nullable()->after('store_name');
            $table->string('store_email')->nullable()->after('store_url');
            $table->string('license_description')->nullable()->after('license_status');
            $table->json('license_data')->nullable()->after('license_gateways')->comment('last WHMCS verify.php response');
            $table->timestamp('license_verified_at')->nullable()->after('license_checked_at')->comment('last time WHMCS said Active');
            $table->string('license_checked_via', 20)->nullable()->after('license_verified_at')->comment('settings, payment or admin');
            $table->index('license_status');
            $table->index('license_key');
        });
    }

    public function down(): void
    {
        Schema::table('ecwid_stores', function (Blueprint $table) {
            $table->dropIndex(['license_status']);
            $table->dropIndex(['license_key']);
            $table->dropColumn(['store_name', 'store_url', 'store_email', 'license_description', 'license_data', 'license_verified_at', 'license_checked_via']);
        });
    }
};
