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
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->text('description')->nullable();
            $table->json('capabilities')->nullable(); // Store capabilities as JSON (chat, voice, file upload, etc)
            $table->string('personality')->nullable();
            $table->text('system_prompt')->nullable();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('active'); // active, inactive
            $table->string('access_role')->default('member'); // owner, admin, member - determines data access
            $table->integer('max_context_length')->default(4000);
            $table->string('model')->default('gpt-3.5-turbo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
