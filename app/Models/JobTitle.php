<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTitle extends Model
{
    /** @use HasFactory<\Database\Factories\JobTitleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get all users with this job title.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
