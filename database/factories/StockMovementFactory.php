<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'location_id' => Location::factory(),
            'user_id' => null,
            'type' => 'receipt',
            'quantity_change' => '1.0000',
            'quantity_before' => '0.0000',
            'quantity_after' => '1.0000',
            'occurred_at' => now(),
            'reason' => null,
            'reference' => null,
        ];
    }
}
