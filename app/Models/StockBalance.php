<?php

namespace App\Models;

use Database\Factories\StockBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['item_id', 'location_id', 'quantity'])]
class StockBalance extends Model
{
    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (StockBalance $balance): void {
            if ($balance->isDirty(['item_id', 'location_id'])) {
                throw new LogicException('A stock balance cannot be moved to another item or location.');
            }
        });

        static::deleting(function (StockBalance $balance): never {
            throw new LogicException('Stock balances cannot be deleted. Record an adjustment instead.');
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }
}
