<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait to prevent hard deletes on models.
 *
 * This trait blocks all forceDelete operations to ensure audit integrity.
 * Models using this trait can only be soft-deleted.
 *
 * Security: Ensures complete audit trail preservation.
 */
trait PreventsHardDeletes
{
    /**
     * Boot the trait.
     */
    public static function bootPreventsHardDeletes(): void
    {
        static::forceDeleting(function (Model $model) {
            // Log the attempt for security monitoring
            logger()->warning('Hard delete attempt blocked', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'user_id' => auth()->id(),
            ]);

            return false; // Prevent the delete
        });
    }
}
