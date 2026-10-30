<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    use HasFactory;

    public const REASONS = [
        'spam' => 'Spam or promotional content',
        'harassment' => 'Harassment or hate speech',
        'misinformation' => 'Misleading or false information',
        'copyright' => 'Copyright violation or plagiarism',
        'inappropriate' => 'Inappropriate or harmful content',
        'other' => 'Other issue',
    ];

    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'notes',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->user();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }
}
