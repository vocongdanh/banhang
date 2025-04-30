<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIAgent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'avatar',
        'description',
        'capabilities',
        'personality',
        'system_prompt',
        'business_id',
        'is_public',
        'status',
        'access_role',
        'max_context_length',
        'model',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capabilities' => 'array',
    ];

    /**
     * Get the business that owns the AI agent.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the conversations for the AI agent.
     */
    public function conversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    /**
     * Check if the agent has a specific capability.
     *
     * @param string $capability
     * @return bool
     */
    public function hasCapability($capability)
    {
        return in_array($capability, $this->capabilities ?? []);
    }
}
