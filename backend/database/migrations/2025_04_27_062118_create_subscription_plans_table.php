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
    Schema::create('subscription_plans', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->integer('max_users')->default(1);
        $table->integer('max_files')->default(100);
        $table->integer('max_storage_mb')->default(1000);
        $table->boolean('can_use_vector_search')->default(true);
        $table->boolean('can_use_ai_chatbot')->default(false);
        $table->boolean('can_use_messenger_bot')->default(false);
        $table->boolean('can_use_zalo_bot')->default(false);
        $table->boolean('can_connect_shopee')->default(false);
        $table->boolean('can_connect_tiktok')->default(false);
        $table->boolean('can_connect_google_drive')->default(false);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
