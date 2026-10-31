<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes, HasUniqueSlug;

    public string $slugField = 'slug';
    public string $slugSourceField = 'title';

    protected $fillable = [
        'user_id',
        'category_id',
        'series_id',
        'series_order',
        'title',
        'slug',
        'excerpt',
        'cover_image_path',
        'seo_title',
        'seo_description',
        'content_json',
        'content_html',
        'reading_time_minutes',
        'status',
        'is_featured',
        'published_at',
        'views_count',
        'likes_count',
        'comments_count',
    ];

    protected $appends = [
        'is_updated',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'reading_time_minutes' => 'integer',
            'status' => PostStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'series_id' => 'integer',
            'series_order' => 'integer',
        ];
    }

    /**
     * Get the normalized cover image URL or root-relative path.
     */
    public function getCoverImagePathAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        // Normalize local storage URLs to root-relative path
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

    /**
     * Normalize content HTML to ensure images inside posts use root-relative storage URLs.
     */
    public function getContentHtmlAttribute(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        return preg_replace('#https?://(?:localhost|127\.0\.0\.1)(?::\d+)?/storage/#i', '/storage/', $value);
    }

    /**
     * Normalize content JSON to ensure TipTap editor images use root-relative storage URLs.
     */
    public function getContentJsonAttribute($value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = $value;
        // Unpack if string (handle single or double encoded JSON)
        while (is_string($decoded)) {
            $unpacked = json_decode($decoded, true);
            if ($unpacked === null && json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $decoded = $unpacked;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $jsonStr = json_encode($decoded);
        if (preg_match('#https?://(?:localhost|127\.0\.0\.1)(?::\d+)?/storage/#i', $jsonStr)) {
            $jsonStr = preg_replace('#https?://(?:localhost|127\.0\.0\.1)(?::\d+)?/storage/#i', '/storage/', $jsonStr);
            $cleanDecoded = json_decode($jsonStr, true);
            return is_array($cleanDecoded) ? $cleanDecoded : $decoded;
        }

        return $decoded;
    }

    /**
     * Model boot logic.
     */
    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            // Compute reading_time_minutes (≈225 wpm) on save
            $rawText = strip_tags((string) $post->content_html);
            if (empty(trim($rawText)) && ! empty($post->excerpt)) {
                $rawText = $post->excerpt;
            }
            $wordCount = str_word_count($rawText);
            $post->reading_time_minutes = max(1, (int) ceil($wordCount / 225));
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
            $post = $this->where('id', $value)->first();
            if ($post) {
                return $post;
            }
        }

        return $this->where('slug', $value)->firstOrFail();
    }

    /**
     * Check if the post was updated after its original publication.
     */
    public function getIsUpdatedAttribute(): bool
    {
        return $this->published_at && $this->updated_at && $this->updated_at->gt($this->published_at->addMinutes(1));
    }

    /**
     * The author of the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias for author.
     */
    public function author(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Series the post belongs to.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Get the next published post in the same series.
     */
    public function nextInSeries(): ?Post
    {
        if (! $this->series_id || ! $this->series_order) {
            return null;
        }

        return static::published()
            ->where('series_id', $this->series_id)
            ->where('series_order', '>', $this->series_order)
            ->orderBy('series_order', 'asc')
            ->first();
    }

    /**
     * Get the previous published post in the same series.
     */
    public function previousInSeries(): ?Post
    {
        if (! $this->series_id || ! $this->series_order) {
            return null;
        }

        return static::published()
            ->where('series_id', $this->series_id)
            ->where('series_order', '<', $this->series_order)
            ->orderBy('series_order', 'desc')
            ->first();
    }

    /**
     * Category the post belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Tags attached to the post.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag')->withTimestamps();
    }

    /**
     * Comments on the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Likes on the post.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Users who liked the post.
     */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    /**
     * Bookmarks of the post.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Views logged for the post.
     */
    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    /**
     * Dismissals logged for the post.
     */
    public function dismissals(): HasMany
    {
        return $this->hasMany(PostDismissal::class);
    }

    /**
     * Reports filed against this post.
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Scope to filter featured / editor's pick posts.
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Scope to filter published posts.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to order posts by popularity based on views, likes, and comments.
     */
    public function scopePopular(Builder $query): void
    {
        $query->orderByRaw('(views_count + (likes_count * 2) + (comments_count * 3)) DESC')
            ->orderByDesc('views_count');
    }

    /**
     * Scope to eager load standard post relations to prevent N+1 queries.
     */
    public function scopeWithRelations(Builder $query): void
    {
        $query->with(['user', 'category', 'tags', 'series']);
    }
}
