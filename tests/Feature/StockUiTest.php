<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\StockManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertSuccessful();
});

test('stock forms and writes require administrator access', function () {
    foreach (['receive', 'issue'] as $operation) {
        $this->get('/stock/'.$operation)->assertRedirectToRoute('login');
        $this->post('/stock/'.$operation, [])->assertRedirectToRoute('login');
    }
    $this->actingAs(User::factory()->create());
    foreach (['receive', 'issue'] as $operation) {
        $this->get('/stock/'.$operation)->assertForbidden();
        $this->post('/stock/'.$operation, [])->assertForbidden();
    }
    $this->actingAs(User::factory()->administrator()->create());
    $this->get('/stock/receive')->assertOk()->assertSee('An active stock item');
    $this->get('/stock/issue')->assertOk();
});

test('receipt and issue update exact quantities and history with server assigned actor', function () {
    $admin = User::factory()->administrator()->create();
    $other = User::factory()->create();
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $this->actingAs($admin);
    $data = ['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '10.5000', 'submission_key' => (string) Str::uuid(), 'reference' => 'FIC-DELIVERY', 'user_id' => $other->id];
    $this->post('/stock/receive', $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/stock/issue', array_replace($data, ['quantity' => '2.2500', 'submission_key' => (string) Str::uuid()]))->assertSessionHasNoErrors()->assertRedirect();
    expect(StockBalance::sole()->quantity)->toBe('8.2500');
    $movements = StockMovement::orderBy('id')->get();
    expect($movements)->toHaveCount(2);
    expect($movements[0]->quantity_before)->toBe('0.0000')->and($movements[0]->quantity_after)->toBe('10.5000');
    expect($movements[1]->quantity_change)->toBe('-2.2500')->and($movements[1]->quantity_after)->toBe('8.2500');
    expect($movements->pluck('user_id')->unique()->all())->toBe([$admin->id]);
    $this->get(route('balances.index'))->assertSee('8.25');
    $this->get(route('movements.index'))->assertSee('FIC-DELIVERY')->assertSee('2.25');
    $this->post('/stock/issue', array_replace($data, ['quantity' => '9', 'submission_key' => (string) Str::uuid()]))->assertSessionHasErrors('quantity');
    expect(StockBalance::sole()->quantity)->toBe('8.2500');
    $this->assertDatabaseCount('stock_movements', 2);
});

test('same stock submission is recorded once and altered retries are rejected', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $data = ['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '1.1250', 'submission_key' => (string) Str::uuid()];
    $this->post('/stock/receive', $data)->assertSessionHasNoErrors();
    $this->post('/stock/receive', $data)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'Already recorded'));
    $this->post('/stock/receive', array_replace($data, ['quantity' => '2']))->assertSessionHasErrors('submission_key');
    $this->post('/stock/issue', $data)->assertSessionHasErrors('submission_key');
    expect(StockBalance::sole()->quantity)->toBe('1.1250');
    $this->assertDatabaseCount('stock_movements', 1);
});

test('stock writes reject invalid decimal quantities without creating balances', function (mixed $quantity) {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $this->post('/stock/receive', ['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => $quantity, 'submission_key' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['0', '-1', '1.00001', '1e3', '1,000', '100000000000000', 1.25]);

test('stock UI rejects asset inactive and archived records', function (string $invalid) {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    match ($invalid) {
        'asset' => $item->update(['tracking_type' => 'asset']),
        'inactive item' => $item->update(['is_active' => false]),
        'archived item' => $item->delete(),
        'inactive location' => $location->update(['is_active' => false]),
        'archived location' => $location->delete(),
    };
    $field = str_contains($invalid, 'location') ? 'location_id' : 'item_id';
    $this->post('/stock/receive', ['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '1', 'submission_key' => (string) Str::uuid()])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['asset', 'inactive item', 'archived item', 'inactive location', 'archived location']);

test('movement insertion failure rolls back balance and allows retry', function () {
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $key = (string) Str::uuid();
    $connection = DB::connection();
    $connection->statement("CREATE TRIGGER fail_demo_movement BEFORE INSERT ON stock_movements BEGIN SELECT RAISE(ABORT, 'fictional failure'); END");
    try {
        app(StockManager::class)->receive($item, $location, '1.0001', submissionKey: $key);
        $this->fail('Expected movement insertion failure.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('fictional failure');
    } finally {
        $connection->statement('DROP TRIGGER fail_demo_movement');
    }
    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
    app(StockManager::class)->receive($item, $location, '1.0001', submissionKey: $key);
    expect(StockBalance::sole()->quantity)->toBe('1.0001');
});

test('stock request requires its submission token and keeps invalid form input', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $this->get('/stock/receive')->assertOk();
    $key = (string) Str::uuid();
    $this->from('/stock/receive')->post('/stock/receive', [
        'item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '0', 'submission_key' => $key,
    ])->assertRedirect('/stock/receive')->assertSessionHasErrors('quantity');
    $response = $this->withCookie(session()->getName(), session()->getId())->get('/stock/receive')->assertOk()->assertSee($key);
    $response->assertSee('Quantity must be non-zero');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('stock submission requires a valid token', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock']);
    $location = Location::factory()->create();
    $this->post('/stock/receive', ['item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '1'])
        ->assertSessionHasErrors('submission_key');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('issues only use receipts for the exact item and location', function (string $scenario) {
    $this->actingAs(User::factory()->administrator()->create());
    $item = Item::factory()->create(['tracking_type' => 'stock', 'unit_of_measure' => 'metre']);
    $source = Location::factory()->create(['name' => 'Fictional source store']);
    $destination = Location::factory()->create(['name' => 'Fictional other store']);
    if ($scenario !== 'before receipt') {
        $this->post('/stock/receive', [
            'item_id' => $item->id, 'location_id' => $source->id,
            'quantity' => '10.5000', 'submission_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
    $location = $scenario === 'different location' ? $destination : $source;
    $quantity = $scenario === 'exceeds balance' ? '10.5001' : '2.2500';
    $response = $this->from('/stock/issue')->post('/stock/issue', [
        'item_id' => $item->id, 'location_id' => $location->id,
        'quantity' => $quantity, 'submission_key' => (string) Str::uuid(),
    ]);
    if ($scenario === 'same location') {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(StockBalance::sole()->quantity)->toBe('8.2500');
        $this->assertDatabaseCount('stock_movements', 2);
    } else {
        $response->assertRedirect('/stock/issue')->assertSessionHasErrors(['quantity' => 'Insufficient stock at this location.']);
        $this->withCookie(session()->getName(), session()->getId())->get('/stock/issue')->assertOk()
            ->assertSee('Insufficient stock at this location.')->assertSee('Available:');
        $this->assertDatabaseCount('stock_movements', $scenario === 'before receipt' ? 0 : 1);
        $this->assertDatabaseCount('stock_balances', $scenario === 'before receipt' ? 0 : 1);
        if ($scenario !== 'before receipt') {
            expect(StockBalance::sole()->quantity)->toBe('10.5000');
        }
    }
})->with(['same location', 'before receipt', 'different location', 'exceeds balance']);

test('an asset created through the item form is excluded from stock entry and rejected server side', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $category = ItemCategory::factory()->create();
    $location = Location::factory()->create();
    $this->post('/items', [
        'code' => 'FIC-LAPTOP', 'name' => 'Fictional laptop', 'item_category_id' => $category->id,
        'tracking_type' => 'asset', 'unit_of_measure' => 'piece', 'is_active' => '1',
    ])->assertRedirectToRoute('items.index')->assertSessionHasNoErrors();
    $item = Item::sole();
    expect($item->tracking_type)->toBe('asset');
    foreach (['receive', 'issue'] as $operation) {
        $this->get('/stock/'.$operation)->assertOk()->assertDontSee('FIC-LAPTOP')
            ->assertSee('Available:');
        $this->post('/stock/'.$operation, [
            'item_id' => $item->id, 'location_id' => $location->id,
            'quantity' => '1', 'submission_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('item_id');
    }
    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('administrator writes reject requests without a CSRF token', function () {
    $this->app->bind(PreventRequestForgery::class, function (Application $app): PreventRequestForgery {
        return new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };
    });
    $this->actingAs(User::factory()->administrator()->create());
    $this->post('/categories', [])->assertStatus(419);
    $this->post('/stock/receive', [])->assertStatus(419);
    $this->post('/stock/issue', [])->assertStatus(419);
    $this->assertDatabaseCount('stock_movements', 0);
});
