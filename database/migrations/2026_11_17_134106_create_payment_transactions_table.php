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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('ecwid_app_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('gateway', 50);
            $table->string('order_id')->comment('id used with the Ecwid orders API');
            $table->string('order_number')->nullable();
            $table->decimal('amount', 14, 4);
            $table->string('currency', 3);
            $table->unsignedBigInteger('gateway_amount')->nullable()->comment('in the gateway minor unit');
            $table->string('gateway_currency', 3)->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->text('gateway_settings')->nullable()->comment('encrypted snapshot of merchant settings');
            $table->text('ecwid_token')->comment('encrypted');
            $table->text('return_url');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('ecwid_synced_at')->nullable();
            $table->string('ecwid_payment_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
