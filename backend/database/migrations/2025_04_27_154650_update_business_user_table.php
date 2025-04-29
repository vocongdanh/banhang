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
        Schema::table('business_user', function (Blueprint $table) {
            // Thêm khóa ngoại đến bảng users và businesses
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->after('user_id')->constrained()->onDelete('cascade');
            
            // Thêm cột role để phân quyền người dùng trong doanh nghiệp
            $table->enum('role', ['owner', 'admin', 'member'])->after('business_id')->default('member');
            
            // Thêm cột status để kiểm soát trạng thái người dùng trong doanh nghiệp
            $table->enum('status', ['active', 'inactive', 'pending'])->after('role')->default('active');
            
            // Thêm cột joined_at để lưu thời điểm người dùng tham gia doanh nghiệp
            $table->timestamp('joined_at')->after('status')->nullable();
            
            // Thêm cột last_active_at để theo dõi hoạt động của người dùng
            $table->timestamp('last_active_at')->after('joined_at')->nullable();
            
            // Tạo unique để không có record trùng lặp
            $table->unique(['user_id', 'business_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            // Xóa unique constraint
            $table->dropUnique(['user_id', 'business_id']);
            
            // Xóa các cột
            $table->dropColumn([
                'user_id',
                'business_id',
                'role',
                'status',
                'joined_at',
                'last_active_at'
            ]);
        });
    }
};
