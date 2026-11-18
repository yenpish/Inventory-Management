<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['code', 'name', 'parent_id', 'description', 'is_active'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            if (! $location->isDirty('parent_id')) {
                return;
            }

            $ancestorId = $location->parent_id;
            $visitedIds = [];

            if ($location->getKey() !== null) {
                $visitedIds[(string) $location->getKey()] = true;
            }

            while ($ancestorId !== null) {
                if (isset($visitedIds[(string) $ancestorId])) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A location cannot belong to a circular parent hierarchy.',
                    ]);
                }

                $visitedIds[(string) $ancestorId] = true;
                $ancestorId = $location->newQuery()->withTrashed()
                    ->whereKey($ancestorId)->value('parent_id');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
