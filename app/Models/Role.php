<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    // A role can be assigned to many users (many-to-many)
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withTimestamps();
    }
}
