<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'facebook_id',
        'google_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Kiểm tra nếu user là superadmin
     */
    public function isSuperAdmin()
    {
        return $this->role === 'superadmin';
    }
    
    /**
     * Lấy tất cả business mà user thuộc về
     */
    public function businesses()
    {
        return $this->belongsToMany(Business::class)->withPivot('role');
    }
    
    /**
     * Kiểm tra quyền hạn của user trong một business cụ thể
     */
    public function hasPermission($permission, $businessId = null)
    {
        // Superadmin luôn có tất cả quyền
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Nếu không có business_id, kiểm tra quyền global
        if (!$businessId) {
            // Implement logic kiểm tra quyền global
            return false;
        }
        
        // Kiểm tra quyền trong business
        $businessUser = $this->businesses()
            ->where('businesses.id', $businessId)
            ->first();
            
        if (!$businessUser) {
            return false;
        }
        
        // Dựa vào role trong pivot table để kiểm tra quyền
        $role = $businessUser->pivot->role;
        
        // Implement logic kiểm tra quyền dựa trên role
        switch ($role) {
            case 'owner':
                return true; // Owner có mọi quyền
            case 'admin':
                // Admin có hầu hết quyền ngoại trừ một số quyền đặc biệt
                return $permission !== 'delete_business';
            case 'member':
                // Member chỉ có quyền đọc và một số quyền hạn chế
                return in_array($permission, ['read', 'upload']);
            default:
                return false;
        }
    }
}
