<?php

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['item_id', 'location_id', 'user_id', 'type', 'quantity_change', 'quantity_before', 'quantity_after', 'occurred_at', 'reason', 'reference'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (StockMovement $movement): never {
            throw new LogicException('Stock movements are immutable. Record a correcting movement instead.');
        });

        static::deleting(function (StockMovement $movement): never {
            throw new LogicException('Stock movements cannot be deleted.');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:4',
            'quantity_before' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
