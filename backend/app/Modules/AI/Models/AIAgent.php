<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Business\Models\Company;
use App\Modules\Business\Models\Department;

class AIAgent extends Model
{
    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'description',
        'type', // customer_service, business_agent
        'model', // gpt-4, gpt-3.5-turbo, etc.
        'temperature',
        'max_tokens',
        'system_prompt',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'json',
        'is_active' => 'boolean',
        'temperature' => 'float',
        'max_tokens' => 'integer'
    ];

    /**
     * Get the company that owns the AI agent.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the department that owns the AI agent.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the vector collections associated with the AI agent.
     */
    public function vectorCollections()
    {
        return $this->hasMany(VectorCollection::class);
    }

    /**
     * Get the integrations associated with the AI agent.
     */
    public function integrations()
    {
        return $this->hasMany(AgentIntegration::class);
    }
} 