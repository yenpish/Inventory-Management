<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\User;
use App\QuantityFormatter;
use App\StockManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertSuccessful();
});

test('forms retain their section highlight in desktop and mobile navigation', function (string $kind, bool $editing) {
    $this->actingAs(User::factory()->administrator()->create());
    $record = match ($kind) {
        'categories' => ItemCategory::factory()->create(),
        'locations' => Location::factory()->create(),
        'items' => Item::factory()->create(),
        default => null,
    };
    $stockForm = in_array($kind, ['receive', 'issue'], true);
    $path = $stockForm ? '/stock/'.$kind : '/'.$kind.($editing ? '/'.$record->id.'/edit' : '/create');
    $response = $this->get($path)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $currentLinks = $xpath->query('//nav[@aria-label="Main navigation"]/a[@aria-current="page"]');
    expect($currentLinks->length)->toBe(2);
    foreach ($currentLinks as $link) {
        expect($link->getAttribute('href'))->toBe(route($stockForm ? 'balances.index' : $kind.'.index'));
    }
})->with([
    ['categories', false], ['categories', true], ['locations', false], ['locations', true],
    ['items', false], ['items', true], ['receive', false], ['issue', false],
]);

test('catalog action labels render without Blade fragments for administrators and viewers', function (bool $administrator) {
    $user = User::factory()->create(['is_admin' => $administrator]);
    $category = ItemCategory::factory()->create(['description' => 'Fictional category description']);
    $location = Location::factory()->create(['description' => 'Fictional location description']);
    $item = Item::factory()->for($category, 'category')->create(['description' => 'Fictional item description']);
    $archivedCategory = ItemCategory::factory()->create();
    $archivedLocation = Location::factory()->create();
    $archivedItem = Item::factory()->create();
    $archivedCategory->delete();
    $archivedLocation->delete();
    $archivedItem->delete();
    $this->actingAs($user);
    foreach (['categories' => [$category, $archivedCategory], 'locations' => [$location, $archivedLocation], 'items' => [$item, $archivedItem]] as $kind => [$record, $archived]) {
        $response = $this->get(route($kind.'.index', ['status' => 'all']))->assertOk()->assertSee($record->description);
        expect($response->getContent())->not->toMatch('/@(can|endcan|unless|endunless|if|endif|else|foreach|endforeach)\b/');
        $editUrl = route('catalog.edit', ['kind' => $kind, 'record' => $record->id]);
        $administrator ? $response->assertSee($editUrl) : $response->assertDontSee($editUrl);
        $response->assertDontSee(route('catalog.edit', ['kind' => $kind, 'record' => $archived->id]));
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//a[contains(@href, "/edit")]') as $action) {
            expect(preg_replace('/\s+/', ' ', trim($action->textContent)))->toStartWith('Edit ')->not->toContain('@');
        }
    }
})->with([true, false]);

test('rendered inventory forms have clear field guidance and prerequisite links', function () {
    $this->actingAs(User::factory()->administrator()->create());
    $this->get('/items/create')->assertOk()->assertSee('Add a category first.')
        ->assertSee(route('catalog.create', ['kind' => 'categories']))->assertSee('(required)')->assertSee('(optional)')
        ->assertSee('Stock: quantities')->assertSee('Asset: individual equipment')->assertSee('metre');
    $this->get('/locations/create')->assertOk()->assertSee('Choose a containing location, or leave blank for top-level.');
    $this->get('/categories/create')->assertOk()->assertSee('STATIONERY');
    foreach (['receive', 'issue'] as $operation) {
        $this->get('/stock/'.$operation)->assertOk()->assertSee('Add stock item')->assertSee('Add location')
            ->assertSee(route('catalog.create', ['kind' => 'items']))->assertSee('PO-2026-014');
    }
});

test('all populated forms and inventory pages render cleanly with escaped data', function () {
    $this->actingAs(User::factory()->administrator()->create(['name' => 'Fictional Reviewer']));
    $category = ItemCategory::factory()->create(['code' => 'FIC-SUPPLIES', 'name' => 'Fictional office supplies']);
    $location = Location::factory()->create(['code' => 'FIC-STORE', 'name' => 'Fictional store']);
    Location::factory()->create(['code' => 'FIC-ANNEX', 'name' => 'Fictional annexe']);
    $item = Item::factory()->for($category, 'category')->create(['code' => 'FIC-CABLE', 'name' => 'Fictional cable', 'tracking_type' => 'stock', 'unit_of_measure' => 'metre', 'description' => '<script>fictional()</script>']);
    app(StockManager::class)->receive($item, $location, '10.5000', reference: 'FIC-DELIVERY');
    $pages = [
        'category-create' => '/categories/create', 'category-edit' => '/categories/'.$category->id.'/edit',
        'location-create' => '/locations/create', 'location-edit' => '/locations/'.$location->id.'/edit',
        'item-create' => '/items/create', 'item-edit' => '/items/'.$item->id.'/edit',
        'receive' => '/stock/receive', 'issue' => '/stock/issue',
        'categories' => '/categories', 'locations' => '/locations', 'items' => '/items',
        'balances' => '/stock-balances', 'history' => '/movements', 'dashboard' => '/dashboard',
    ];
    $rendered = [];
    foreach ($pages as $name => $path) {
        $response = $this->get($path)->assertOk();
        expect($response->getContent())->not->toMatch('/@(can|endcan|unless|endunless|if|endif|else|foreach|endforeach|csrf|selected|disabled|checked)\b/');
        $response->assertDontSee('<script>fictional()</script>', false);
        $rendered[$name] = $response->getContent();
    }
    $this->from('/categories/create')->post('/categories', [])->assertSessionHasErrors(['name', 'code']);
    $rendered['category-errors'] = $this->withCookie(session()->getName(), session()->getId())
        ->get('/categories/create')->assertOk()->assertSee('Please check the following:')->getContent();
    $this->from('/stock/issue')->post('/stock/issue', [
        'item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '99',
        'submission_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('quantity');
    $rendered['issue-errors'] = $this->withCookie(session()->getName(), session()->getId())
        ->get('/stock/issue')->assertOk()->assertSee('Insufficient stock at this location.')->getContent();
    $this->get('/stock/issue')->assertViewHas('availability', [$item->id.':'.$location->id => '10.5'])
        ->assertSee('Available:')->assertSee('FIC-ANNEX');
    $this->get('/items/'.$item->id.'/edit')->assertSee('Type and unit are fixed once stock is recorded.');
    if (getenv('INVENTORY_REVIEW_RENDER') === '1') {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inventory-ui-review';
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.DIRECTORY_SEPARATOR.'rendered-pages.json', json_encode($rendered, JSON_THROW_ON_ERROR));
    }
});

test('each catalog create and edit form displays its validation feedback', function (string $kind, bool $editing) {
    $this->actingAs(User::factory()->administrator()->create());
    $record = match ($kind) {
        'categories' => ItemCategory::factory()->create(),
        'locations' => Location::factory()->create(),
        'items' => Item::factory()->create(),
    };
    $formPath = '/'.$kind.($editing ? '/'.$record->id.'/edit' : '/create');
    $payload = ['code' => '', 'name' => '', 'is_active' => '1'];
    $this->from($formPath);
    $response = $editing ? $this->put('/'.$kind.'/'.$record->id, $payload) : $this->post('/'.$kind, $payload);
    $response->assertRedirect($formPath)->assertSessionHasErrors(['code', 'name']);
    $page = $this->withCookie(session()->getName(), session()->getId())->get($formPath)
        ->assertOk()->assertSee('Please check the following:')->assertSee('The code field is required.')
        ->assertSee('The name field is required.')->assertSee('aria-invalid="true"', false);
    expect($page->getContent())->not->toMatch('/@(endcan|can|endunless|unless|endif|if|csrf)\b/');
})->with([
    ['categories', false], ['categories', true], ['locations', false],
    ['locations', true], ['items', false], ['items', true],
]);

test('compact audit covers guest administrator viewer empty populated and successful stock screens', function () {
    $pages = ['login' => $this->get('/login')->assertOk()->getContent()];
    $this->from('/login')->post('/login', [])->assertSessionHasErrors(['email', 'password']);
    $pages['login-errors'] = $this->withCookie(session()->getName(), session()->getId())->get('/login')->assertOk()->getContent();
    $admin = User::factory()->administrator()->create(['name' => 'Fictional Administrator']);
    $viewer = User::factory()->create(['name' => 'Fictional Viewer']);
    $paths = ['dashboard' => '/dashboard', 'categories' => '/categories', 'items' => '/items', 'locations' => '/locations', 'current-stock' => '/stock-balances', 'stock-history' => '/movements'];
    foreach (['admin' => $admin, 'viewer' => $viewer] as $role => $user) {
        $this->actingAs($user);
        foreach ($paths as $name => $path) {
            $pages[$role.'-empty-'.$name] = $this->get($path)->assertOk()->getContent();
        }
    }
    $this->actingAs($admin);
    foreach (['categories', 'items', 'locations'] as $kind) {
        $pages['empty-create-'.$kind] = $this->get('/'.$kind.'/create')->assertOk()->getContent();
    }
    foreach (['receive', 'issue'] as $operation) {
        $pages['empty-'.$operation] = $this->get('/stock/'.$operation)->assertOk()->getContent();
    }
    $category = ItemCategory::factory()->create(['name' => 'Fictional office supplies', 'code' => 'FIC-SUPPLIES']);
    $location = Location::factory()->create(['name' => 'Fictional store', 'code' => 'FIC-STORE']);
    $emptyLocation = Location::factory()->create(['name' => 'Fictional annexe', 'code' => 'FIC-ANNEX']);
    $item = Item::factory()->for($category, 'category')->create(['name' => 'Fictional cable', 'code' => 'FIC-CABLE', 'tracking_type' => 'stock', 'unit_of_measure' => 'metre']);
    foreach ([['receive', $location, '10.5000'], ['issue', $location, '2.2500'], ['receive', $emptyLocation, '1.0000'], ['issue', $emptyLocation, '1.0000']] as [$operation, $place, $quantity]) {
        $this->post('/stock/'.$operation, ['item_id' => $item->id, 'location_id' => $place->id, 'quantity' => $quantity, 'submission_key' => (string) Str::uuid(), 'reference' => 'FIC-ORDER-2026'])
            ->assertRedirect()->assertSessionHasNoErrors();
        expect(session('status'))->toContain(($operation === 'receive' ? 'Received ' : 'Issued ').QuantityFormatter::format($quantity).' metre of Fictional cable')
            ->not->toContain('.5000', '.2500', '.0000');
        $pages['success-'.$operation.'-'.$place->id] = $this->withCookie(session()->getName(), session()->getId())
            ->get('/stock-balances')->assertOk()->getContent();
    }
    expect($item->stockBalances()->where('location_id', $location->id)->sole()->quantity)->toBe('8.2500');
    expect($item->stockBalances()->where('location_id', $emptyLocation->id)->sole()->quantity)->toBe('0.0000');
    foreach (['admin' => $admin, 'viewer' => $viewer] as $role => $user) {
        $this->actingAs($user);
        foreach ($paths as $name => $path) {
            $response = $this->get($path)->assertOk();
            $pages[$role.'-populated-'.$name] = $response->getContent();
            if ($role === 'viewer') {
                $response->assertDontSee('Save item')->assertDontSee('Receive Stock');
            }
        }
    }
    $this->actingAs($admin);
    $this->get('/stock-balances')->assertSee('>8.25</p>', false)->assertSee('>0</p>', false)->assertDontSee('8.2500');
    $this->get('/movements')->assertSee('+10.5')->assertSee('-2.25')->assertDontSee('10.5000');
    foreach (['categories' => $category, 'items' => $item, 'locations' => $location] as $kind => $record) {
        $pages['create-'.$kind] = $this->get('/'.$kind.'/create')->assertOk()->getContent();
        $pages['edit-'.$kind] = $this->get('/'.$kind.'/'.$record->id.'/edit')->assertOk()->getContent();
    }
    foreach (['receive', 'issue'] as $operation) {
        $pages[$operation] = $this->get('/stock/'.$operation)->assertOk()->getContent();
    }
    foreach ($pages as $html) {
        expect($html)->not->toMatch('/@(endcan|can|endunless|endif|foreach|csrf)\b/');
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//input[@required] | //select[@required] | //textarea[@required]') as $control) {
            $label = $xpath->query('//label[@for="'.$control->getAttribute('id').'"]')->item(0);
            expect($label?->textContent)->toContain('*', '(required)');
        }
    }
    if (getenv('INVENTORY_REVIEW_RENDER') === '1') {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inventory-ui-review';
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.DIRECTORY_SEPARATOR.'audit-pages.json', json_encode($pages, JSON_THROW_ON_ERROR));
    }
});
