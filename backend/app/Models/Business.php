<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'assistant_id',
        'vector_store_id',
        'ai_settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ai_settings' => 'array',
    ];

    /**
     * Get the owner of the business.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the users associated with the business.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'business_user', 'business_id', 'user_id');
    }

    /**
     * Get the files for the business.
     */
    public function files()
    {
        return $this->hasMany(File::class);
    }

    /**
     * Get the subscription của business
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    /**
     * Check if the business has an active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->subscription()->where('end_date', '>=', now())->exists();
    }

    /**
     * Check if the business has a specific feature
     */
    public function hasFeature($feature)
    {
        $subscription = $this->subscription;
        
        if (!$subscription) {
            return false;
        }
        
        $plan = $subscription->subscriptionPlan;
        
        return $plan && $plan->$feature === true;
    }

    /**
     * Kiểm tra nếu business đã đạt giới hạn file
     */
    public function hasReachedFileLimit()
    {
        $subscription = $this->subscription;
        
        if (!$subscription) {
            return true;
        }
        
        $plan = $subscription->subscriptionPlan;
        
        if (!$plan) {
            return true;
        }
        
        $currentFileCount = $this->files()->count();
        
        return $currentFileCount >= $plan->max_files;
    }
}
