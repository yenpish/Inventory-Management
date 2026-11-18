<?php

use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\StockManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);

    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])
        ->assertExitCode(0);
});

test('receipts preserve fractional quantities and complete movement metadata', function () {
    $this->freezeTime();
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $user = User::factory()->create();
    $date = CarbonImmutable::parse('2026-11-17 08:30:00', 'UTC');

    $movement = app(StockManager::class)->receive(
        $item, $location, '12.3456', $user,
        reason: 'Supplier delivery', reference: 'PO-100', occurredAt: $date,
    )->fresh();

    expect($movement->type)->toBe('receipt');
    expect($movement->quantity_change)->toBe('12.3456');
    expect($movement->quantity_before)->toBe('0.0000');
    expect($movement->quantity_after)->toBe('12.3456');
    expect($movement->occurred_at->equalTo($date))->toBeTrue();
    expect($movement->reason)->toBe('Supplier delivery');
    expect($movement->reference)->toBe('PO-100');
    expect($movement->user->is($user))->toBeTrue();
    expect($movement->item->is($item))->toBeTrue();
    expect($movement->location->is($location))->toBeTrue();
    expect($user->stockMovements->modelKeys())->toBe([$movement->id]);

    $balance = $item->stockBalances->sole();
    expect($balance->quantity)->toBe('12.3456');
    expect($balance->item->is($item))->toBeTrue();
    expect($balance->location->is($location))->toBeTrue();
    expect($location->stockBalances->modelKeys())->toBe([$balance->id]);
    expect($item->stockMovements->modelKeys())->toBe([$movement->id]);
    expect($location->stockMovements->modelKeys())->toBe([$movement->id]);
});

test('receiving repeatedly and issuing all stock uses exact decimal arithmetic', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00:00', 'UTC'));
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $manager = app(StockManager::class);
    $manager->receive($item, $location, '0.1000');
    $manager->receive($item, $location, '0.2000');

    $movement = $manager->issue($item, $location, '0.3000')->fresh();

    expect($movement->type)->toBe('issue');
    expect($movement->quantity_change)->toBe('-0.3000');
    expect($movement->quantity_before)->toBe('0.3000');
    expect($movement->quantity_after)->toBe('0.0000');
    expect($movement->user_id)->toBeNull();
    expect($movement->occurred_at->equalTo(now()))->toBeTrue();
    expect(StockBalance::sole()->quantity)->toBe('0.0000');
    expect(StockMovement::orderBy('id')->pluck('quantity_change')->all())
        ->toBe(['0.1000', '0.2000', '-0.3000']);
    $this->assertDatabaseCount('stock_balances', 1);
});

test('adjustments apply signed changes and retain a reason', function (string $change, string $after) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $manager = app(StockManager::class);
    $manager->receive($item, $location, '5');

    $movement = $manager->adjust($item, $location, $change, reason: ' Physical count ')->fresh();

    expect($movement->type)->toBe('adjustment');
    expect($movement->quantity_before)->toBe('5.0000');
    expect($movement->quantity_change)->toBe($change);
    expect($movement->quantity_after)->toBe($after);
    expect($movement->reason)->toBe('Physical count');
    expect(StockBalance::sole()->quantity)->toBe($after);
    $this->assertDatabaseCount('stock_movements', 2);
})->with(['increase' => ['0.1250', '5.1250'], 'decrease' => ['-0.1250', '4.8750']]);

test('adjustments require a nonblank reason', function (?string $reason) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    expect(fn () => app(StockManager::class)->adjust($item, $location, '1', reason: $reason))
        ->toThrow(ValidationException::class, 'An adjustment requires a reason.');

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with([null, '', '   ']);

test('stock operations reject invalid quantities without writing records', function (mixed $quantity, string $operation) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    expect(fn () => app(StockManager::class)->{$operation}($item, $location, $quantity, reason: 'Count'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with([
    'zero' => '0',
    'negative zero' => '-0.0000',
    'excess precision' => '0.00001',
    'oversized' => '100000000000000',
    'exponent' => '1e3',
    'nonnumeric' => 'abc',
    'float' => 0.1,
    'boolean' => true,
    'null' => null,
    'array' => [[]],
])->with(['receive', 'issue', 'adjust']);

test('receipts and issues reject negative quantities', function (string $operation) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    expect(fn () => app(StockManager::class)->{$operation}($item, $location, '-1'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['receive', 'issue']);

test('operations reject asset items even when a stale model says stock', function (string $operation) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    Item::whereKey($item->id)->update(['tracking_type' => 'asset']);

    expect(fn () => app(StockManager::class)->{$operation}($item, $location, '1', reason: 'Count'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['receive', 'issue', 'adjust']);

test('operations reject inactive or archived records read from the database', function (string $target, string $state, string $operation) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $record = $target === 'item' ? $item : $location;
    $storedRecord = $record->fresh();

    if ($state === 'archived') {
        $storedRecord->delete();
    } else {
        $storedRecord->update(['is_active' => false]);
    }

    expect(fn () => app(StockManager::class)->{$operation}($item, $location, '1', reason: 'Count'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['item', 'location'])->with(['inactive', 'archived'])->with(['receive', 'issue', 'adjust']);

test('issuing or reducing more than the available stock preserves balance and history', function (string $operation, string $quantity) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $manager = app(StockManager::class);
    $original = $manager->receive($item, $location, '1.2500')->fresh()->getAttributes();

    expect(fn () => $manager->{$operation}($item, $location, $quantity, reason: 'Count'))
        ->toThrow(ValidationException::class, 'Insufficient stock at this location.');

    expect(StockBalance::sole()->quantity)->toBe('1.2500');
    expect(StockMovement::sole()->getAttributes())->toBe($original);
})->with(['issue' => ['issue', '1.2501'], 'adjustment' => ['adjust', '-1.2501']]);

test('issuing from an empty location creates neither a balance nor a movement', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    expect(fn () => app(StockManager::class)->issue($item, $location, '0.0001'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('balances remain separate per item and location', function () {
    $item = Item::factory()->create();
    $otherItem = Item::factory()->create();
    $location = Location::factory()->create();
    $otherLocation = Location::factory()->create();
    $manager = app(StockManager::class);
    $manager->receive($item, $location, '10');
    $manager->receive($item, $otherLocation, '2');
    $manager->receive($otherItem, $location, '3');

    $manager->issue($item, $location, '1');

    expect($item->stockBalances()->where('location_id', $location->id)->sole()->quantity)->toBe('9.0000');
    expect($item->stockBalances()->where('location_id', $otherLocation->id)->sole()->quantity)->toBe('2.0000');
    expect($otherItem->stockBalances()->sole()->quantity)->toBe('3.0000');
    $this->assertDatabaseCount('stock_balances', 3);
    $this->assertDatabaseCount('stock_movements', 4);
});

test('movement insertion failure rolls back both new and existing balances', function (bool $existingBalance) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $manager = app(StockManager::class);

    if ($existingBalance) {
        $manager->receive($item, $location, '2');
    }

    $user = User::factory()->create();
    $user->fresh()->delete();

    expect(fn () => $manager->receive($item, $location, '1', user: $user))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('stock_balances', $existingBalance ? 1 : 0);
    $this->assertDatabaseCount('stock_movements', $existingBalance ? 1 : 0);

    if ($existingBalance) {
        expect(StockBalance::sole()->quantity)->toBe('2.0000');
    }
})->with(['first receipt' => false, 'existing balance' => true]);

test('movement history cannot be updated or deleted through model operations', function (string $operation) {
    $movement = StockMovement::factory()->create();
    $original = $movement->fresh()->getAttributes();

    expect(fn () => $operation === 'update'
        ? $movement->update(['reason' => 'Rewrite history'])
        : $movement->delete())->toThrow(LogicException::class);

    expect($movement->fresh()->getAttributes())->toBe($original);
})->with(['update', 'delete']);

test('balances cannot be duplicated or deleted', function () {
    $balance = StockBalance::factory()->create();

    expect(fn () => StockBalance::factory()->create(['item_id' => $balance->item_id, 'location_id' => $balance->location_id]))
        ->toThrow(QueryException::class);
    expect(fn () => $balance->delete())->toThrow(LogicException::class);

    $this->assertModelExists($balance);
    $this->assertDatabaseCount('stock_balances', 1);
});

test('balance ownership cannot be changed', function (string $key, string $modelClass) {
    $balance = StockBalance::factory()->create();
    $other = $modelClass::factory()->create();

    expect(fn () => $balance->update([$key => $other->id]))->toThrow(LogicException::class);

    expect($balance->fresh()->{$key})->not->toBe($other->id);
})->with(['item' => ['item_id', Item::class], 'location' => ['location_id', Location::class]]);

test('history and balances retain their relationships after item and location archival', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $movement = app(StockManager::class)->receive($item, $location, '0.0001');
    $item->delete();
    $location->delete();

    expect($movement->fresh()->item->is($item))->toBeTrue();
    expect($movement->fresh()->location->is($location))->toBeTrue();
    expect(StockBalance::sole()->item->is($item))->toBeTrue();
    expect(StockBalance::sole()->location->is($location))->toBeTrue();
    expect(StockBalance::sole()->quantity)->toBe('0.0001');
    $this->assertDatabaseCount('stock_movements', 1);

    expect(fn () => $item->forceDelete())->toThrow(QueryException::class);
    expect(fn () => $location->forceDelete())->toThrow(QueryException::class);
});

test('movement user references are preserved against hard deletion', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $user = User::factory()->create();
    app(StockManager::class)->receive($item, $location, 1, user: $user);

    expect(fn () => $user->delete())->toThrow(QueryException::class);

    $this->assertModelExists($user);
});

test('receiving beyond the supported balance range leaves history untouched', function () {
    $balance = StockBalance::factory()->create(['quantity' => '99999999999999']);

    expect(fn () => app(StockManager::class)->receive($balance->item, $balance->location, '1'))
        ->toThrow(ValidationException::class, 'The resulting balance exceeds the supported quantity range.');

    expect($balance->fresh()->quantity)->toBe('99999999999999.0000');
    $this->assertDatabaseCount('stock_movements', 0);
});

test('operations reject unsaved responsible users', function () {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $user = User::factory()->make();

    expect(fn () => app(StockManager::class)->receive($item, $location, '1', user: $user))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('oversized metadata is rejected without changing stock', function (string $field, int $length) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $metadata = [$field => str_repeat('x', $length)];

    expect(fn () => app(StockManager::class)->receive($item, $location, '1', ...$metadata))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['reference' => ['reference', 256], 'reason' => ['reason', 65536]]);

test('operations reject missing item or location records', function (string $target) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $record = $target === 'item' ? $item : $location;
    $record->fresh()->forceDelete();

    expect(fn () => app(StockManager::class)->receive($item, $location, '1'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('stock_balances', 0);
    $this->assertDatabaseCount('stock_movements', 0);
})->with(['item', 'location']);

test('movement dates use datetime storage and remain unchanged after later stock operations', function (string $operation) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();
    $manager = app(StockManager::class);
    $date = CarbonImmutable::parse('2026-11-17 03:15:00', 'UTC');
    $original = $manager->receive($item, $location, '5.0001', occurredAt: $date);

    $manager->{$operation}($item, $location, '0.0001', reason: 'Count');

    expect(Schema::getColumnType('stock_movements', 'occurred_at'))->toBe('datetime');
    expect($original->fresh()->occurred_at->getTimestamp())->toBe($date->getTimestamp());
    expect($original->fresh()->quantity_after)->toBe('5.0001');
    $this->assertDatabaseCount('stock_movements', 2);
})->with(['receive', 'issue', 'adjust']);

test('timezone-aware movement inputs are normalized to the same UTC instant', function (string $date) {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    $movement = app(StockManager::class)->receive(
        $item, $location, '0.0001', occurredAt: new DateTimeImmutable($date),
    )->fresh();

    expect($movement->occurred_at->format('Y-m-d H:i:sP'))->toBe('2026-11-18 09:30:00+00:00');
    expect(DB::table('stock_movements')->where('id', $movement->id)->value('occurred_at'))
        ->toBe('2026-11-18 09:30:00');
    expect($movement->quantity_change)->toBe('0.0001');
})->with(['2026-11-18T17:30:00+08:00', '2026-11-18T04:30:00-05:00', '2026-11-18T09:30:00Z']);

test('movement datetime does not automatically change when another column is updated', function () {
    $date = CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC');
    $movement = StockMovement::factory()->create(['occurred_at' => $date]);

    DB::table('stock_movements')->where('id', $movement->id)->update(['reference' => 'Metadata correction']);

    expect($movement->fresh()->occurred_at->getTimestamp())->toBe($date->getTimestamp());
});

test('application and configured mysql sessions use UTC', function () {
    $databaseConfig = require base_path('config/database.php');

    expect(config('app.timezone'))->toBe('UTC');
    expect($databaseConfig['connections']['mysql']['timezone'])->toBe('+00:00');
    expect($databaseConfig['connections']['mariadb']['timezone'])->toBe('+00:00');
});
