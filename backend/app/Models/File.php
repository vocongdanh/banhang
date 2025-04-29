<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'filename',
        'path',
        'type',
        'data_type',
        'size',
        'user_id',
        'business_id',
        'embedding_id',
        'batch_id',
        'vector_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'business_id' => 'integer',
    ];

    /**
     * Append custom attributes to the model's array form.
     *
     * @var array
     */
    protected $appends = ['url', 'uploaded_at'];

    /**
     * Get the file's download URL.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return url('api/files/' . $this->id . '/download');
    }

    /**
     * Get the file's uploaded at date in a readable format.
     *
     * @return string
     */
    public function getUploadedAtAttribute()
    {
        return $this->created_at->format('Y-m-d H:i:s');
    }

    /**
     * Get the user that owns the file.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the business that owns the file.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
} 