<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('???-########'),
            'name' => fake()->words(3, true),
            'item_category_id' => ItemCategory::factory(),
            'tracking_type' => 'stock',
            'unit_of_measure' => 'kg',
            'description' => null,
            'is_active' => true,
        ];
    }
}
