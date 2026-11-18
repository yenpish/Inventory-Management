<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);

    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])
        ->assertExitCode(0);
});

test('items belong to a category and support both tracking types', function (string $trackingType) {
    $category = ItemCategory::factory()->create();
    $attributes = Item::factory()->make([
        'item_category_id' => $category->id,
        'tracking_type' => $trackingType,
        'description' => 'Measured or individually tracked supplies.',
    ])->getAttributes();

    $item = Item::create($attributes)->fresh();

    expect($item->category->is($category))->toBeTrue();
    expect($category->items->modelKeys())->toBe([$item->id]);
    expect($item->tracking_type)->toBe($trackingType);
    expect($item->unit_of_measure)->toBe('kg');
    expect($item->description)->toBe('Measured or individually tracked supplies.');
})->with(['stock', 'asset']);

test('locations support roots and children', function () {
    $parent = Location::factory()->create();
    $attributes = Location::factory()->make(['parent_id' => $parent->id])->getAttributes();

    $child = Location::create($attributes)->fresh();

    expect($parent->parent)->toBeNull();
    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children->modelKeys())->toBe([$child->id]);
});

test('locations reject direct self-parenting without changing the saved parent', function () {
    $location = Location::factory()->create();

    expect(fn () => $location->update(['parent_id' => (string) $location->id]))
        ->toThrow(ValidationException::class, 'A location cannot belong to a circular parent hierarchy.');

    expect($location->fresh()->parent_id)->toBeNull();
});

test('locations reject multi-level cycles including archived ancestors', function (bool $archiveMiddle) {
    $root = Location::factory()->create();
    $middle = Location::factory()->create(['parent_id' => $root->id]);
    $leaf = Location::factory()->create(['parent_id' => $middle->id]);

    if ($archiveMiddle) {
        $middle->delete();
    }

    expect(fn () => $root->update(['parent_id' => $leaf->id]))
        ->toThrow(ValidationException::class);

    expect($root->fresh()->parent_id)->toBeNull();
    expect($middle->fresh()->parent_id)->toBe($root->id);
    expect($leaf->fresh()->parent_id)->toBe($middle->id);
})->with(['active ancestors' => false, 'archived ancestor' => true]);

test('locations can be reparented to another tree while retaining their children', function () {
    $originalParent = Location::factory()->create();
    $newParent = Location::factory()->create();
    $location = Location::factory()->create(['parent_id' => $originalParent->id]);
    $child = Location::factory()->create(['parent_id' => $location->id]);
    $location->load('parent');

    $location->update(['parent_id' => $newParent->id]);

    expect($location->fresh()->parent->is($newParent))->toBeTrue();
    expect($newParent->children->modelKeys())->toBe([$location->id]);
    expect($originalParent->children)->toBeEmpty();
    expect($child->fresh()->parent->is($location))->toBeTrue();
});

test('locations can become roots again', function () {
    $parent = Location::factory()->create();
    $location = Location::factory()->create(['parent_id' => $parent->id]);

    $location->update(['parent_id' => null]);

    expect($location->fresh()->parent)->toBeNull();
    expect($parent->children)->toBeEmpty();
});

test('locations reject attachment to an already circular ancestor chain', function () {
    $first = Location::factory()->create();
    $second = Location::factory()->create(['parent_id' => $first->id]);
    DB::table('locations')->where('id', $first->id)->update(['parent_id' => $second->id]);
    $location = Location::factory()->create();

    expect(fn () => $location->update(['parent_id' => $first->id]))
        ->toThrow(ValidationException::class);

    expect($location->fresh()->parent_id)->toBeNull();
});

test('foundational records default to active and cast inactive status', function (string $modelClass) {
    $attributes = $modelClass::factory()->make()->getAttributes();
    unset($attributes['is_active']);

    $model = $modelClass::create($attributes)->fresh();

    expect($model->is_active)->toBeTrue();
    expect($model->description)->toBeNull();

    $model->update(['is_active' => false]);

    expect($model->fresh()->is_active)->toBeFalse();
})->with([ItemCategory::class, Item::class, Location::class]);

test('codes remain unique even after archival', function (string $modelClass) {
    $model = $modelClass::factory()->create();
    $model->delete();

    expect(fn () => $modelClass::factory()->create(['code' => $model->code]))
        ->toThrow(QueryException::class);
})->with([ItemCategory::class, Item::class, Location::class]);

test('items require an existing category', function (mixed $categoryId) {
    expect(fn () => Item::factory()->create(['item_category_id' => $categoryId]))
        ->toThrow(QueryException::class);
})->with(['missing' => null, 'unknown' => 999999]);

test('locations reject nonexistent parents', function () {
    expect(fn () => Location::factory()->create(['parent_id' => 999999]))
        ->toThrow(QueryException::class);
});

test('the database rejects unsupported tracking types', function () {
    $item = Item::factory()->create();

    expect(fn () => DB::table('items')->where('id', $item->id)->update(['tracking_type' => 'other']))
        ->toThrow(QueryException::class);
});

test('archival retains records and supports restoration', function (string $modelClass) {
    $model = $modelClass::factory()->create();

    $model->delete();

    $this->assertSoftDeleted($model);
    expect($modelClass::find($model->id))->toBeNull();

    $model->restore();

    $this->assertModelExists($model);
    expect($modelClass::find($model->id))->not->toBeNull();
})->with([ItemCategory::class, Item::class, Location::class]);

test('archived categories remain accessible to existing items', function () {
    $item = Item::factory()->create();
    $category = $item->category;

    $category->delete();

    $this->assertModelExists($item);
    expect($item->fresh()->category->is($category))->toBeTrue();
});

test('archived parent locations remain accessible to existing children', function () {
    $parent = Location::factory()->create();
    $child = Location::factory()->create(['parent_id' => $parent->id]);

    $parent->delete();

    $this->assertModelExists($child);
    expect($child->fresh()->parent->is($parent))->toBeTrue();
});

test('referenced categories cannot be permanently deleted', function () {
    $item = Item::factory()->create();

    expect(fn () => $item->category->forceDelete())->toThrow(QueryException::class);

    $this->assertModelExists($item->category);
    $this->assertModelExists($item);
});

test('referenced parent locations cannot be permanently deleted', function () {
    $parent = Location::factory()->create();
    $child = Location::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => $parent->forceDelete())->toThrow(QueryException::class);

    $this->assertModelExists($parent);
    $this->assertModelExists($child);
});
