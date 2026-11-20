<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\User;
use App\StockManager;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertSuccessful();
});

test('guests and viewers cannot write or see write controls', function () {
    $this->get('/items/create')->assertRedirectToRoute('login');
    $this->post('/items', [])->assertRedirectToRoute('login');
    $this->actingAs(User::factory()->create());
    foreach (['categories', 'locations', 'items'] as $kind) {
        $this->get('/'.$kind.'/create')->assertForbidden();
        $this->post('/'.$kind, [])->assertForbidden();
        $this->get('/'.$kind.'/1/edit')->assertForbidden();
        $this->put('/'.$kind.'/1', [])->assertForbidden();
    }
    $this->get(route('items.index'))->assertDontSee('Add item');
    $this->get(route('balances.index'))->assertDontSee('Receive Stock');
});

test('administrators create and edit each catalog record without mass assigning privileges', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin);
    foreach (['categories', 'locations', 'items'] as $kind) {
        $this->get('/'.$kind.'/create')->assertOk();
    }
    $this->post('/categories', ['code' => ' fic-cat ', 'name' => 'Fictional supplies', 'is_active' => '1'])->assertRedirectToRoute('categories.index')
        ->assertSessionHas('status', 'Category “Fictional supplies” created.');
    $category = ItemCategory::sole();
    expect($category->code)->toBe('FIC-CAT');
    $this->post('/locations', ['code' => 'FIC-STORE', 'name' => 'Fictional store', 'is_active' => '1'])->assertRedirectToRoute('locations.index');
    $location = Location::sole();
    $payload = ['code' => 'FIC-PAPER', 'name' => 'Fictional paper', 'is_active' => '1', 'item_category_id' => $category->id, 'tracking_type' => 'stock', 'unit_of_measure' => 'box', 'is_admin' => true];
    $this->post('/items', $payload)->assertRedirectToRoute('items.index');
    $item = Item::sole();
    foreach (['categories' => $category, 'locations' => $location, 'items' => $item] as $kind => $record) {
        $this->get(route($kind.'.index'))->assertOk()->assertSee(route('catalog.edit', ['kind' => $kind, 'record' => $record->id]));
        $this->get('/'.$kind.'/'.$record->id.'/edit')->assertOk();
        $data = ['code' => $record->code, 'name' => 'Fictional updated', 'is_active' => '0'];
        if ($kind === 'items') {
            $data += ['item_category_id' => $category->id, 'tracking_type' => 'stock', 'unit_of_measure' => 'box'];
        }
        $this->put('/'.$kind.'/'.$record->id, $data)->assertRedirectToRoute($kind.'.index');
        expect($record->fresh()->name)->toBe('Fictional updated')->and($record->fresh()->is_active)->toBeFalse();
    }
    $viewer = new User(['name' => 'Fictional viewer', 'email' => 'viewer@example.test', 'password' => 'password', 'is_admin' => true]);
    expect($viewer->is_admin)->toBeNull();
});

test('catalog validation reserves archived codes and rejects invalid relationships', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $category = ItemCategory::factory()->create(['code' => 'FIC-RESERVED']);
    $category->delete();
    $this->post('/categories', ['code' => 'fic-reserved', 'name' => 'Fictional duplicate', 'is_active' => 1])->assertSessionHasErrors('code');
    $this->post('/items', ['code' => 'FIC-ITEM', 'name' => 'Fictional', 'is_active' => 1, 'item_category_id' => $category->id, 'tracking_type' => 'stock', 'unit_of_measure' => 'box'])->assertSessionHasErrors('item_category_id');
    $this->post('/categories', [])->assertSessionHasErrors(['code', 'name', 'is_active']);
});

test('location forms preserve hierarchy validation and allow valid reparenting', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $root = Location::factory()->create();
    $child = Location::factory()->create(['parent_id' => $root->id]);
    $leaf = Location::factory()->create(['parent_id' => $child->id]);
    $data = ['code' => $root->code, 'name' => $root->name, 'is_active' => 1];
    foreach ([$root->id, $leaf->id] as $parent) {
        $this->put('/locations/'.$root->id, $data + ['parent_id' => $parent])->assertSessionHasErrors('parent_id');
        expect($root->fresh()->parent_id)->toBeNull();
    }
    $this->put('/locations/'.$leaf->id, ['code' => $leaf->code, 'name' => $leaf->name, 'is_active' => 1, 'parent_id' => $root->id])->assertSessionHasNoErrors();
    expect($leaf->fresh()->parent_id)->toBe($root->id);
});

test('editing retains archived references and prevents reinterpretation of recorded stock', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    app(StockManager::class)->receive($item, $location, '1.1250');
    $category = $item->category;
    $category->delete();
    $data = ['code' => $item->code, 'name' => 'Fictional renamed', 'is_active' => 1, 'item_category_id' => $category->id, 'tracking_type' => 'stock', 'unit_of_measure' => $item->unit_of_measure];
    $this->get('/items/'.$item->id.'/edit')->assertOk()->assertSee('(archived)');
    $this->put('/items/'.$item->id, $data)->assertSessionHasNoErrors();
    $this->put('/items/'.$item->id, array_replace($data, ['tracking_type' => 'asset']))->assertSessionHasErrors('tracking_type');
    $this->put('/items/'.$item->id, array_replace($data, ['unit_of_measure' => 'different']))->assertSessionHasErrors('tracking_type');
    expect($item->fresh()->tracking_type)->toBe('stock');
    $item->delete();
    $this->get('/items/'.$item->id.'/edit')->assertNotFound();
});

test('promotion requires explicit confirmation and preserves existing credentials', function (bool $confirm) {
    $user = User::factory()->create();
    $hash = $user->password;
    $this->artisan('app:promote-admin', ['email' => $user->email])->expectsConfirmation('Grant administrator access to '.$user->email.' in inventory_management?', $confirm ? 'yes' : 'no')->assertExitCode($confirm ? 0 : 1);
    expect($user->fresh()->is_admin)->toBe($confirm)->and($user->fresh()->password)->toBe($hash);
})->with([true, false]);

test('promotion refuses noninteractive elevation', function () {
    $user = User::factory()->create();
    $this->artisan('app:promote-admin', ['email' => $user->email, '--no-interaction' => true])->assertFailed();
});

test('promotion refuses unknown accounts and other database targets', function () {
    $this->artisan('app:promote-admin', ['email' => 'missing@example.test'])
        ->expectsOutputToContain('Account not found')->assertFailed();
    DB::purge('sqlite');
    config(['database.connections.sqlite.database' => 'other_application']);
    $this->artisan('app:promote-admin', ['email' => 'missing@example.test'])
        ->expectsOutputToContain('Refusing to change an account')->assertFailed();
});
