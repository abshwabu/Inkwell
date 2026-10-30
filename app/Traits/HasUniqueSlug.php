<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasUniqueSlug
{
    /**
     * Boot the trait to hook into model creating event.
     */
    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function ($model) {
            $slugField = $model->getSlugField();
            $sourceField = $model->getSlugSourceField();

            // Use explicitly set slug if provided, else generate from source field
            $sourceText = !empty($model->{$slugField}) ? $model->{$slugField} : $model->{$sourceField};

            if (empty($sourceText)) {
                $sourceText = class_basename($model);
            }

            $baseSlug = Str::slug($sourceText);
            if (empty($baseSlug)) {
                $baseSlug = Str::lower(class_basename($model));
            }

            $model->{$slugField} = static::generateUniqueSlug($baseSlug, $slugField, $model->getKey());
        });
    }

    /**
     * Get the slug column name.
     */
    public function getSlugField(): string
    {
        return $this->slugField ?? 'slug';
    }

    /**
     * Get the source column name used to generate slug.
     */
    public function getSlugSourceField(): string
    {
        return $this->slugSourceField ?? 'title';
    }

    /**
     * Generate a unique slug by appending incremental counter on collision.
     */
    public static function generateUniqueSlug(string $baseSlug, string $slugField = 'slug', $ignoreId = null): string
    {
        $slug = $baseSlug;
        $count = 1;

        $hasSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive(static::class));

        while (true) {
            $query = static::query()->where($slugField, $slug);

            if ($ignoreId !== null) {
                $query->where((new static)->getKeyName(), '!=', $ignoreId);
            }

            if ($hasSoftDeletes) {
                $query->withTrashed();
            }

            if (!$query->exists()) {
                break;
            }

            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}
