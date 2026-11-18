<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use App\StockManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
});

test('inventory pages provide navigation and sensible empty states', function (string $route, string $heading, string $emptyText) {
    $response = $this->actingAs(User::factory()->create())->get(route($route));

    $response->assertOk()->assertSee($heading)->assertSee($emptyText)
        ->assertSee('View only')->assertSee('Account menu');
    foreach (['dashboard', 'items.index', 'categories.index', 'locations.index', 'balances.index', 'movements.index'] as $link) {
        $response->assertSee('href="'.route($link).'"', false);
    }

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([
    ['dashboard', 'Dashboard', 'No stock transactions yet'],
    ['items.index', 'Items', 'No items found'],
    ['categories.index', 'Categories', 'No categories found'],
    ['locations.index', 'Locations', 'No locations found'],
    ['balances.index', 'Current Stock', 'No stock found'],
    ['movements.index', 'Stock History', 'No stock transactions found'],
]);

test('dashboard metrics and recent activity reflect real records', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    app(StockManager::class)->receive($item, $location, '2.5000');
    Item::factory()->for($item->category, 'category')->create(['tracking_type' => 'asset']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertViewHas('metrics', ['items' => 2, 'categories' => 1, 'locations' => 1, 'movements' => 1])
        ->assertViewHas('positiveBalances', 1)->assertViewHas('zeroBalances', 0)
        ->assertSee($item->name)->assertSee('+2.5000');
});

test('item searches combine category and tracking filters without leaking other rows', function () {
    $category = ItemCategory::factory()->create();
    $match = Item::factory()->for($category, 'category')->create(['name' => 'Precision cable', 'tracking_type' => 'stock']);
    Item::factory()->for($category, 'category')->create(['name' => 'Precision laptop', 'tracking_type' => 'asset']);
    Item::factory()->create(['name' => 'Precision component']);

    $this->actingAs(User::factory()->create())->get(route('items.index', [
        'q' => 'Precision', 'category' => $category->id, 'tracking_type' => 'stock',
    ]))->assertOk()->assertViewHas('items', fn ($items): bool => $items->modelKeys() === [$match->id]);
});

test('archived and inactive catalog records are distinguishable through filters', function (string $status) {
    $active = Item::factory()->create();
    $inactive = Item::factory()->create(['is_active' => false]);
    $archived = Item::factory()->create();
    $archived->delete();
    $expected = match ($status) {
        'active' => [$active->id],
        'inactive' => [$inactive->id],
        'archived' => [$archived->id],
    };

    $this->actingAs(User::factory()->create())->get(route('items.index', ['status' => $status]))
        ->assertOk()->assertViewHas('items', fn ($items): bool => $items->modelKeys() === $expected);
})->with(['active', 'inactive', 'archived']);

test('categories and locations support searching and retain hierarchical context', function () {
    $category = ItemCategory::factory()->create(['name' => 'Network equipment']);
    $parent = Location::factory()->create(['name' => 'Main building']);
    $child = Location::factory()->create(['name' => 'Server room', 'parent_id' => $parent->id]);
    $parent->delete();
    $this->actingAs(User::factory()->create());

    $this->get(route('categories.index', ['q' => 'Network']))->assertOk()
        ->assertViewHas('categories', fn ($rows): bool => $rows->modelKeys() === [$category->id]);
    $this->get(route('locations.index', ['q' => 'Server']))->assertOk()
        ->assertViewHas('locations', fn ($rows): bool => $rows->modelKeys() === [$child->id])
        ->assertSee('Main building')->assertSee('Parent location archived');
});

test('item pagination preserves search and category filters', function () {
    $category = ItemCategory::factory()->create();
    Item::factory()->for($category, 'category')->count(25)
        ->sequence(fn (Sequence $sequence): array => ['code' => sprintf('PART-%03d', $sequence->index), 'name' => 'Paging part'])
        ->create();

    $response = $this->actingAs(User::factory()->create())->get(route('items.index', [
        'q' => 'Paging', 'category' => $category->id, 'page' => 2,
    ]))->assertOk()->assertSee('PART-020')->assertDontSee('PART-000');

    $paginator = $response->viewData('items');
    expect($paginator->count())->toBe(5);
    expect($paginator->total())->toBe(25);
    expect($paginator->url(1))->toContain('q=Paging')->toContain('category='.$category->id);
});

test('balances filter by location and preserve exact quantities and archived references', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $other = Location::factory()->create();
    app(StockManager::class)->receive($item, $location, '0.0001');
    app(StockManager::class)->receive($item, $other, '1');
    $item->delete();
    $location->delete();

    $this->actingAs(User::factory()->create())->get(route('balances.index', ['location' => $location->id, 'balance' => 'positive']))
        ->assertOk()->assertViewHas('balances', fn ($rows): bool => $rows->count() === 1 && $rows->first()->location_id === $location->id)
        ->assertSee('0.0001')->assertSee('Archived item')->assertSee('Archived location');
});

test('movement date filters use Malaysia days while displaying retained transaction details', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $user = User::factory()->create();
    $manager = app(StockManager::class);
    $manager->receive($item, $location, '2', occurredAt: CarbonImmutable::parse('2026-11-17 15:59:59', 'UTC'));
    $match = $manager->issue($item, $location, '0.1250', user: $user, reference: 'REQ-012', occurredAt: CarbonImmutable::parse('2026-11-17 16:00:00', 'UTC'));
    $manager->adjust($item, $location, '0.0001', reason: 'Count', occurredAt: CarbonImmutable::parse('2026-11-18 16:00:00', 'UTC'));
    $manager->issue($item, $location, '0.0001', occurredAt: CarbonImmutable::parse('2026-11-17 15:59:59', 'UTC'));
    $manager->issue($item, $location, '0.0001', occurredAt: CarbonImmutable::parse('2026-11-18 16:00:00', 'UTC'));

    $this->actingAs(User::factory()->create(['name' => 'Fictional reader']))->get(route('movements.index', [
        'from' => '2026-11-18', 'to' => '2026-11-18', 'type' => 'issue', 'location' => $location->id,
    ]))->assertOk()->assertViewHas('movements', fn ($rows): bool => $rows->modelKeys() === [$match->id])
        ->assertSee('-0.1250')->assertSee('1.8750')->assertSee('REQ-012')->assertSee($user->name)
        ->assertSee('18 Nov 2026')->assertSee('00:00:00');
});

test('malicious content is escaped in user and inventory fields', function () {
    $dangerous = '<script>alert("test")</script>';
    Item::factory()->create(['name' => $dangerous, 'description' => $dangerous]);
    $user = User::factory()->create(['name' => $dangerous]);

    $this->actingAs($user)->get(route('items.index'))->assertOk()
        ->assertSee($dangerous)->assertDontSee($dangerous, false);
});

test('search input remains bound data rather than executable SQL', function () {
    Item::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('items.index', ['q' => "' OR 1=1 --"]))
        ->assertOk()->assertViewHas('items', fn ($items): bool => $items->isEmpty());
});

test('invalid filters are rejected before queries are composed', function (array $filters, string $field) {
    $this->actingAs(User::factory()->create())->from(route('items.index'))
        ->get(route('items.index', $filters))->assertRedirect()->assertSessionHasErrors($field);
})->with([
    [['q' => ['not-a-string']], 'q'],
    [['status' => 'unknown'], 'status'],
    [['tracking_type' => 'other'], 'tracking_type'],
    [['category' => 9999], 'category'],
    [['page' => -1], 'page'],
    [['from' => 'invalid'], 'from'],
    [['from' => '2026-11-19', 'to' => '2026-11-18'], 'to'],
]);

test('authenticated inventory browsing does not issue write queries', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    app(StockManager::class)->receive($item, $location, '1.2500');
    $this->actingAs(User::factory()->create());
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\\s*(insert|update|delete|replace|alter|drop|create)\\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    foreach (['dashboard', 'items.index', 'categories.index', 'locations.index', 'balances.index', 'movements.index'] as $route) {
        $this->get(route($route))->assertOk();
    }

    expect($writes)->toBe([]);
    expect(StockBalance::sole()->quantity)->toBe('1.2500');
    $this->assertDatabaseCount('stock_movements', 1);
});

test('stock-changing and catalog-changing HTTP actions are unavailable', function (string $path, int $status) {
    $this->actingAs(User::factory()->create())->post($path, ['quantity' => '10'])->assertStatus($status);

    $this->assertDatabaseCount('stock_movements', 0);
    $this->assertDatabaseCount('stock_balances', 0);
})->with([['/stock-balances', 405], ['/movements', 405], ['/items', 405], ['/stock/receive', 404], ['/stock/issue', 404], ['/stock/adjust', 404]]);

test('category and location searches find fictional records by code', function (string $modelClass, string $route, string $viewKey) {
    $match = $modelClass::factory()->create(['code' => 'FIC-OFFICE', 'name' => 'Fictional office records']);
    $modelClass::factory()->create(['code' => 'FIC-OTHER', 'name' => 'Fictional unrelated records']);

    $this->actingAs(User::factory()->create())->get(route($route, ['q' => 'FIC-OFFICE']))
        ->assertOk()->assertViewHas($viewKey, fn ($rows): bool => $rows->modelKeys() === [$match->id]);
})->with([
    [ItemCategory::class, 'categories.index', 'categories'],
    [Location::class, 'locations.index', 'locations'],
]);

test('category and location pagination retains search and status filters', function (string $modelClass, string $route, string $viewKey) {
    $records = $modelClass::factory()->count(23)
        ->sequence(fn (Sequence $sequence): array => [
            'code' => sprintf('FIC-%03d', $sequence->index),
            'name' => sprintf('Fictional office %03d', $sequence->index),
        ])->create();
    $modelClass::factory()->create(['name' => 'Fictional office inactive', 'is_active' => false]);
    $archived = $modelClass::factory()->create(['name' => 'Fictional office archived']);
    $archived->delete();

    $response = $this->actingAs(User::factory()->create())->get(route($route, [
        'q' => 'Fictional office', 'status' => 'active', 'page' => 2,
    ]))->assertOk();

    $paginator = $response->viewData($viewKey);
    expect($paginator->modelKeys())->toBe($records->slice(20)->values()->modelKeys());
    expect($paginator->total())->toBe(23);
    parse_str(parse_url($paginator->url(1), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['q' => 'Fictional office', 'status' => 'active', 'page' => '1']);
})->with([
    [ItemCategory::class, 'categories.index', 'categories'],
    [Location::class, 'locations.index', 'locations'],
]);

test('current stock search finds fictional item and location codes', function (string $search, int $expectedCount) {
    $item = Item::factory()->create(['name' => 'Fictional printer paper', 'code' => 'FIC-PAPER']);
    $otherItem = Item::factory()->create(['name' => 'Fictional binders', 'code' => 'FIC-BINDER']);
    $north = Location::factory()->create(['name' => 'Fictional North Store', 'code' => 'FIC-NORTH']);
    $south = Location::factory()->create(['name' => 'Fictional South Store', 'code' => 'FIC-SOUTH']);
    $manager = app(StockManager::class);
    $manager->receive($item, $north, '2.1250');
    $manager->receive($item, $south, '0.0001');
    $manager->receive($otherItem, $north, '1');

    $this->actingAs(User::factory()->create())->get(route('balances.index', ['q' => $search]))
        ->assertOk()->assertViewHas('balances', fn ($rows): bool => $rows->total() === $expectedCount);
})->with([['FIC-PAPER', 2], ['FIC-NORTH', 2], ['FIC-BINDER', 1], ['Fictional South', 1], ['No fictional match', 0]]);

test('current stock combines location searches with in-stock and out-of-stock filters', function (string $level, string $expectedQuantity) {
    $paper = Item::factory()->create(['name' => 'Fictional printer paper']);
    $binders = Item::factory()->create(['name' => 'Fictional office binders']);
    $north = Location::factory()->create(['code' => 'FIC-NORTH']);
    $south = Location::factory()->create(['code' => 'FIC-SOUTH']);
    $manager = app(StockManager::class);
    $manager->receive($paper, $north, '5.1250');
    $manager->receive($binders, $north, '1');
    $manager->issue($binders, $north, '1');
    $manager->receive($paper, $south, '3');

    $this->actingAs(User::factory()->create())->get(route('balances.index', [
        'q' => 'Fictional', 'location' => $north->id, 'balance' => $level,
    ]))->assertOk()->assertViewHas('balances', fn ($rows): bool => $rows->total() === 1
        && $rows->first()->location_id === $north->id && $rows->first()->quantity === $expectedQuantity)
        ->assertSee($expectedQuantity);
})->with([['positive', '5.1250'], ['zero', '0.0000']]);

test('current stock pagination keeps its search and stock-level filter', function () {
    $item = Item::factory()->create(['name' => 'Fictional copy paper']);
    $records = StockBalance::factory()->for($item, 'item')->count(23)->create(['quantity' => '1.1250']);
    StockBalance::factory()->for($item, 'item')->create(['quantity' => '0.0000']);
    StockBalance::factory()->create(['quantity' => '9.0000']);

    $response = $this->actingAs(User::factory()->create())->get(route('balances.index', [
        'q' => 'Fictional copy', 'balance' => 'positive', 'page' => 2,
    ]))->assertOk()->assertSee('1.1250');

    $paginator = $response->viewData('balances');
    expect($paginator->modelKeys())->toBe($records->slice(20)->values()->modelKeys());
    expect($paginator->total())->toBe(23);
    parse_str(parse_url($paginator->url(1), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['q' => 'Fictional copy', 'balance' => 'positive', 'page' => '1']);
});

test('stock history pagination combines reference, transaction, location and date filters', function () {
    $item = Item::factory()->create(['name' => 'Fictional shipping labels']);
    $location = Location::factory()->create(['name' => 'Fictional Dispatch Store']);
    $otherLocation = Location::factory()->create();
    $date = CarbonImmutable::parse('2026-11-18 09:00:00', 'UTC');
    $manager = app(StockManager::class);
    $ids = [];

    for ($index = 0; $index < 25; $index++) {
        $ids[] = $manager->receive($item, $location, '0.1250', reference: 'FIC-DELIVERY-'.$index, occurredAt: $date)->id;
    }

    $manager->issue($item, $location, '0.1250', reference: 'FIC-DELIVERY-ISSUE', occurredAt: $date);
    $manager->receive($item, $otherLocation, '1', reference: 'FIC-DELIVERY-OTHER', occurredAt: $date);
    $response = $this->actingAs(User::factory()->create())->get(route('movements.index', [
        'q' => 'FIC-DELIVERY', 'type' => 'receipt', 'location' => $location->id,
        'from' => '2026-11-18', 'to' => '2026-11-18', 'page' => 2,
    ]))->assertOk()->assertSee('+0.1250');

    $paginator = $response->viewData('movements');
    expect($paginator->modelKeys())->toBe(array_reverse(array_slice($ids, 0, 5)));
    expect($paginator->total())->toBe(25);
    expect($paginator->url(1))->toContain('q=FIC-DELIVERY')->toContain('type=receipt')
        ->toContain('location='.$location->id)->toContain('from=2026-11-18')->toContain('to=2026-11-18');
});

test('stock history search finds fictional item names, codes, references and reasons', function (string $search, int $expectedCount) {
    $item = Item::factory()->create(['name' => 'Fictional label rolls', 'code' => 'FIC-LABEL']);
    $other = Item::factory()->create(['name' => 'Fictional tape', 'code' => 'FIC-TAPE']);
    $location = Location::factory()->create();
    $manager = app(StockManager::class);
    $manager->receive($item, $location, '1', reference: 'FIC-ORDER-004');
    $manager->adjust($item, $location, '0.1250', reason: 'Fictional annual count');
    $manager->receive($other, $location, '2');

    $this->actingAs(User::factory()->create())->get(route('movements.index', ['q' => $search]))
        ->assertOk()->assertViewHas('movements', fn ($rows): bool => $rows->total() === $expectedCount);
})->with([
    ['Fictional label', 2], ['FIC-LABEL', 2], ['FIC-ORDER-004', 1],
    ['Fictional annual count', 1], ['No fictional match', 0],
]);
