<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
        'status',
    ];

    protected $appends = [
        'can_edit',
        'formatted_body',
        'trashed',
    ];

    /**
     * Check if comment is soft deleted.
     */
    public function getTrashedAttribute(): bool
    {
        return $this->trashed();
    }

    /**
     * Check if current authenticated user can edit this comment (within 15 minutes of creation).
     */
    public function getCanEditAttribute(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id && $this->created_at && $this->created_at->diffInMinutes(now()) <= 15;
    }

    /**
     * Format limited safe markdown into HTML.
     */
    public function getFormattedBodyAttribute(): string
    {
        if ($this->trashed()) {
            return '<em class="text-gray-400 italic">[Comment deleted by user]</em>';
        }

        $text = htmlspecialchars((string) $this->body, ENT_QUOTES, 'UTF-8');

        // Bold: **text**
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        // Italic: *text* or _text_
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
        // Inline code: `code`
        $text = preg_replace('/`(.+?)`/s', '<code class="rounded bg-gray-100 dark:bg-gray-800 px-1 py-0.5 text-xs font-mono">$1</code>', $text);
        // Blockquote: > quote
        $text = preg_replace('/^&gt;\s*(.+)$/m', '<blockquote class="border-l-2 border-indigo-500 pl-3 italic text-gray-600 dark:text-gray-300">$1</blockquote>', $text);
        // Links: [text](https://url)
        $text = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s\)\"\']+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer nofollow ugc" class="text-indigo-600 underline">$1</a>', $text);

        return nl2br($text);
    }

    /**
     * Reports for this comment.
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    /**
     * Post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * User who authored this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent comment if this is a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Nested replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Scope to filter visible comments.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', CommentStatus::VISIBLE->value);
    }
}
