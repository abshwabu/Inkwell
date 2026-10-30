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
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes, HasUniqueSlug;

    public string $slugField = 'slug';
    public string $slugSourceField = 'title';

    protected $fillable = [
        'user_id',
        'category_id',
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
        ];
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
        $query->with(['user', 'category', 'tags']);
    }
}
