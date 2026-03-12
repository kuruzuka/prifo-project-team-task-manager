<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityLogs
{
    /**
     * Get all activity logs for this model
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Log an activity for this model
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function logActivity(string $activityType, ?array $metadata = null, ?int $actorId = null): ActivityLog
    {
        return $this->activityLogs()->create([
            'activity_type' => $activityType,
            'metadata' => $metadata,
            'actor_id' => $actorId ?? auth()->id(),
        ]);
    }
}
