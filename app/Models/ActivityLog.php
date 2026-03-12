<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'loggable_type',
        'loggable_id',
        'activity_type',
        'metadata',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the parent loggable model (Task, Project, User, etc.)
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the activity
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
