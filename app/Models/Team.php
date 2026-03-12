<?php

namespace App\Models;

use App\Models\Scopes\TeamMembershipScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([TeamMembershipScope::class])]
class Team extends Model
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    // A team can have many users/members (many-to-many)
    public function members()
    {
        return $this->belongsToMany(User::class, 'user_teams')
            ->withTimestamps();
    }

    // A team can work on many projects (many-to-many)
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'team_projects')
            ->withTimestamps();
    }
}
