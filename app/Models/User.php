<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\HasUniqueSlug;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasUniqueSlug;

    public string $slugField = 'username';
    public string $slugSourceField = 'name';

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'avatar_path',
        'role',
        'is_banned',
        'ban_reason',
        'email_digest_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_banned' => 'boolean',
            'email_digest_enabled' => 'boolean',
        ];
    }

    /**
     * Get the user's avatar URL or an initials fallback.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            $path = $this->avatar_path;

            // Normalize local storage URLs to root-relative path
            if (preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(/storage/.*)$#i', $path, $matches)) {
                return $matches[1];
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (str_starts_with($path, '/storage/')) {
                return $path;
            }

            if (str_starts_with($path, 'storage/')) {
                return '/' . $path;
            }

            return '/storage/' . ltrim($path, '/');
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=4F46E5&background=EEF2FF';
    }

    /**
     * Posts authored by the user.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Comments written by the user.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Likes created by the user.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Posts liked by the user.
     */
    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'likes')->withTimestamps();
    }

    /**
     * Bookmarks created by the user.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Posts bookmarked by the user.
     */
    public function bookmarkedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bookmarks')->withTimestamps();
    }

    /**
     * Users that this user is following.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    /**
     * Check if user is following target user.
     */
    public function isFollowing(User $user): bool
    {
        return $this->following()->where('followed_id', $user->id)->exists();
    }

    /**
     * Users who are following this user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    /**
     * Preferred categories selected by the user.
     */
    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_user')->withTimestamps();
    }

    /**
     * Preferred tags selected by the user.
     */
    public function preferredTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_user')->withTimestamps();
    }

    /**
     * Post views by the user.
     */
    public function postViews(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    /**
     * Dismissed posts by the user ("Not interested").
     */
    public function dismissedPosts(): HasMany
    {
        return $this->hasMany(PostDismissal::class);
    }

    /**
     * Reports submitted by this user.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Audit log actions performed by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
