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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('assistant_id')->nullable()->after('description');
            $table->string('vector_store_id')->nullable()->after('assistant_id');
            $table->json('ai_settings')->nullable()->after('vector_store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['assistant_id', 'vector_store_id', 'ai_settings']);
        });
    }
};
