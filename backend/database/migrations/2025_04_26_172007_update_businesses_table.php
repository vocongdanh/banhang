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
            if (!Schema::hasColumn('businesses', 'name')) {
                $table->string('name')->after('id');
            }
            
            if (!Schema::hasColumn('businesses', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('businesses', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('description');
                $table->foreign('owner_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'owner_id')) {
                $table->dropForeign(['owner_id']);
                $table->dropColumn('owner_id');
            }
            
            if (Schema::hasColumn('businesses', 'description')) {
                $table->dropColumn('description');
            }
            
            if (Schema::hasColumn('businesses', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
