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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->integer('max_ai_agents')->default(1)->after('max_files');
            $table->boolean('can_use_ai_voice')->default(false)->after('can_use_ai_chatbot');
            $table->boolean('can_use_ai_image_upload')->default(false)->after('can_use_ai_voice');
            $table->integer('ai_tokens_per_month')->default(100000)->after('can_use_ai_image_upload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('max_ai_agents');
            $table->dropColumn('can_use_ai_voice');
            $table->dropColumn('can_use_ai_image_upload');
            $table->dropColumn('ai_tokens_per_month');
        });
    }
};
