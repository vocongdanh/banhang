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
        Schema::table('files', function (Blueprint $table) {
            // data_type để phân biệt loại dữ liệu: products, orders, customers, suppliers, etc.
            $table->string('data_type')->nullable()->after('type');
            
            // Trường để lưu ID của embedding trong vector store
            $table->string('embedding_id')->nullable()->after('data_type');
            
            // Trường business_id để định danh theo doanh nghiệp
            $table->foreignId('business_id')->nullable()->after('user_id');
            
            // Trạng thái của việc upload lên vector store
            $table->enum('vector_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('embedding_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('data_type');
            $table->dropColumn('embedding_id');
            $table->dropColumn('business_id');
            $table->dropColumn('vector_status');
        });
    }
};
