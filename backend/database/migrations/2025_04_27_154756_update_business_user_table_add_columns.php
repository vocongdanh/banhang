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
        // Kiểm tra xem các cột đã tồn tại chưa
        if (Schema::hasTable('business_user')) {
            $columns = Schema::getColumnListing('business_user');
            
            Schema::table('business_user', function (Blueprint $table) use ($columns) {
                // Thêm khóa ngoại đến bảng users nếu chưa có
                if (!in_array('user_id', $columns)) {
                    $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
                }
                
                // Thêm khóa ngoại đến bảng businesses nếu chưa có
                if (!in_array('business_id', $columns)) {
                    $table->foreignId('business_id')->after('user_id')->constrained()->onDelete('cascade');
                }
                
                // Thêm cột role nếu chưa có
                if (!in_array('role', $columns)) {
                    $table->enum('role', ['owner', 'admin', 'member'])->after('business_id')->default('member');
                }
                
                // Thêm cột status nếu chưa có
                if (!in_array('status', $columns)) {
                    $table->enum('status', ['active', 'inactive', 'pending'])->after('role')->default('active');
                }
                
                // Thêm cột joined_at nếu chưa có
                if (!in_array('joined_at', $columns)) {
                    $table->timestamp('joined_at')->after('status')->nullable();
                }
                
                // Thêm cột last_active_at nếu chưa có
                if (!in_array('last_active_at', $columns)) {
                    $table->timestamp('last_active_at')->after('joined_at')->nullable();
                }
            });
            
            // Tạo unique index nếu chưa có
            if (!Schema::hasIndex('business_user', 'business_user_user_id_business_id_unique')) {
                Schema::table('business_user', function (Blueprint $table) {
                    $table->unique(['user_id', 'business_id']);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            // Không xóa các cột trong rollback để tránh mất dữ liệu
            // Phòng trường hợp cần rollback thì hãy bỏ comment các dòng dưới
            
            /*
            // Xóa unique constraint nếu có
            if (Schema::hasIndex('business_user', 'business_user_user_id_business_id_unique')) {
                $table->dropUnique(['user_id', 'business_id']);
            }
            
            // Xóa các cột
            $columns = Schema::getColumnListing('business_user');
            if (in_array('last_active_at', $columns)) {
                $table->dropColumn('last_active_at');
            }
            if (in_array('joined_at', $columns)) {
                $table->dropColumn('joined_at');
            }
            if (in_array('status', $columns)) {
                $table->dropColumn('status');
            }
            if (in_array('role', $columns)) {
                $table->dropColumn('role');
            }
            if (in_array('business_id', $columns)) {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            }
            if (in_array('user_id', $columns)) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            */
        });
    }
};
