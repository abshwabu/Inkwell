<?php

namespace App\Models;

use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory, HasUniqueSlug;

    public string $slugField = 'slug';
    public string $slugSourceField = 'name';

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Posts tagged with this tag.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag')->withTimestamps();
    }

    /**
     * Users who selected this tag as preferred.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tag_user')->withTimestamps();
    }
}
