<?php

namespace App\Models;

use App\Enums\SeriesStatus;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Series extends Model
{
    use HasFactory, SoftDeletes, HasUniqueSlug;

    protected $table = 'series';

    public string $slugField = 'slug';
    public string $slugSourceField = 'title';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'cover_image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeriesStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // When deleting a series, detach posts (series_id = null, series_order = null) without deleting posts
        static::deleting(function (Series $series) {
            Post::where('series_id', $series->id)->update([
                'series_id' => null,
                'series_order' => null,
            ]);
        });
    }

    /**
     * Resolve route binding by either numeric ID or slug.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            $series = $this->where('id', $value)->first();
            if ($series) {
                return $series;
            }
        }

        return $this->where('slug', $value)->firstOrFail();
    }

    /**
     * Author of the series.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Posts in this series, ordered by series_order.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->orderBy('series_order', 'asc');
    }

    /**
     * Published posts in this series.
     */
    public function publishedPosts(): HasMany
    {
        return $this->hasMany(Post::class)
            ->published()
            ->orderBy('series_order', 'asc');
    }

    /**
     * Followers of this series.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'series_follows')->withTimestamps();
    }

    /**
     * Reading progress records for users.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(SeriesProgress::class);
    }

    /**
     * Scope to include count of published posts.
     */
    public function scopeWithPublishedCount(Builder $query): void
    {
        $query->withCount([
            'posts as published_posts_count' => fn ($q) => $q->published(),
        ]);
    }

    /**
     * Check if a given user is following this series.
     */
    public function isFollowedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->followers()->where('user_id', $user->id)->exists();
    }

    /**
     * Reorder posts in this series within a transaction without unique constraint violation.
     */
    public function reorderParts(array $orderedPostIds): void
    {
        DB::transaction(function () use ($orderedPostIds) {
            // Validate all posts belong to this series
            $posts = Post::where('series_id', $this->id)
                ->whereIn('id', $orderedPostIds)
                ->get()
                ->keyBy('id');

            // Phase 1: Set temporary high offsets to prevent unique index collision
            foreach ($orderedPostIds as $index => $postId) {
                if (isset($posts[$postId])) {
                    Post::where('id', $postId)->update([
                        'series_order' => 100000 + $index,
                    ]);
                }
            }

            // Phase 2: Set clean 1-based sequential order
            foreach ($orderedPostIds as $index => $postId) {
                if (isset($posts[$postId])) {
                    Post::where('id', $postId)->update([
                        'series_order' => $index + 1,
                    ]);
                }
            }
        });
    }

    /**
     * Get the normalized cover image URL or root-relative path.
     */
    public function getCoverImagePathAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(/storage/.*)$#i', $value, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/' . $value;
        }

        return '/storage/' . ltrim($value, '/');
    }

    /**
     * Mutator to ensure stored cover_image_path is normalized.
     */
    public function setCoverImagePathAttribute(?string $value): void
    {
        if (! $value) {
            $this->attributes['cover_image_path'] = null;
            return;
        }

        if (preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(/storage/.*)$#i', $value, $matches)) {
            $this->attributes['cover_image_path'] = $matches[1];
            return;
        }

        $this->attributes['cover_image_path'] = $value;
    }
}
