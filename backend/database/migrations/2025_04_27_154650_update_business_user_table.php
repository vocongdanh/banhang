<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration is now a no-op since its functionality is covered by
     * 2025_04_27_154756_update_business_user_table_add_columns which already ran.
     */
    public function up(): void
    {
        // This migration is now empty because its functionality is already
        // covered by 2025_04_27_154756_update_business_user_table_add_columns
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
