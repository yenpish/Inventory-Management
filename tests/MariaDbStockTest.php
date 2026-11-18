<?php

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\StockManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('MariaDB preserves UTC history, exact decimals, rollback, and concurrent stock consistency', function () {
    if (getenv('M2_MARIADB_VERIFY') !== '1') {
        $this->markTestSkipped('Opt in with M2_MARIADB_VERIFY=1; this test creates and drops its own disposable database.');
    }

    $database = 'm2_stock_verify_'.bin2hex(random_bytes(12));
    $source = (require base_path('config/database.php'))['connections']['mysql'];
    unset($source['url']);
    $source['timezone'] = '+00:00';
    $adminConfig = [...$source, 'database' => ''];
    $testConfig = [...$source, 'database' => $database];
    DB::purge();
    config([
        'database.default' => 'mysql',
        'database.connections' => ['admin' => $adminConfig, 'mysql' => $testConfig],
    ]);
    $admin = DB::connection('admin');
    $created = false;
    $workers = [];

    try {
        if (! preg_match('/\\Am2_stock_verify_[a-f0-9]{24}\\z/', $database)
            || $admin->selectOne('SELECT DATABASE() AS name')->name !== null) {
            throw new RuntimeException('Refusing unsafe database provisioning.');
        }

        $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $created = true;

        expect(DB::connection()->getDatabaseName())->toBe($database);
        expect(DB::selectOne('SELECT DATABASE() AS name')->name)->toBe($database);
        expect(DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone)->toBe('+00:00');

        $correction = 'database/migrations/2026_11_18_035430_convert_stock_movement_occurred_at_to_utc_datetime.php';
        $legacyPaths = array_values(array_filter(
            array_map(fn (string $path): string => 'database/migrations/'.basename($path), glob(database_path('migrations/*.php'))),
            fn (string $path): bool => $path !== $correction,
        ));

        $this->artisan('migrate', ['--database' => 'mysql', '--path' => $legacyPaths, '--no-interaction' => true])
            ->assertExitCode(0);

        $legacyItem = Item::factory()->create();
        $legacyLocation = Location::factory()->create();
        DB::statement("SET SESSION time_zone = '+08:00'");
        $legacy = app(StockManager::class)->receive(
            $legacyItem, $legacyLocation, '1.0001',
            reference: 'Legacy instant', occurredAt: CarbonImmutable::parse('2026-01-01 12:00:00', 'UTC'),
        );
        $originalEpoch = (int) DB::selectOne(
            'SELECT UNIX_TIMESTAMP(occurred_at) AS instant FROM stock_movements WHERE id = ?', [$legacy->id],
        )->instant;
        expect($originalEpoch)->toBe(CarbonImmutable::parse('2026-01-01 04:00:00', 'UTC')->getTimestamp());

        $this->artisan('migrate', ['--database' => 'mysql', '--path' => [$correction], '--no-interaction' => true])
            ->assertExitCode(0);

        expect(DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone)->toBe('+08:00');
        DB::purge('mysql');
        expect(DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone)->toBe('+00:00');
        $definition = DB::selectOne("SHOW COLUMNS FROM stock_movements WHERE Field = 'occurred_at'");
        expect($definition->Type)->toBe('datetime');
        expect($definition->Default)->toBeNull();
        expect(strtolower($definition->Extra))->not->toContain('on update');
        expect(Schema::getColumnType('stock_movements', 'occurred_at'))->toBe('datetime');
        expect($legacy->fresh()->occurred_at->getTimestamp())->toBe($originalEpoch);
        expect($legacy->fresh()->reference)->toBe('Legacy instant');
        expect($legacyItem->stockBalances()->sole()->quantity)->toBe('1.0001');

        DB::table('stock_movements')->where('id', $legacy->id)->update(['reference' => 'Date must remain unchanged']);
        expect($legacy->fresh()->occurred_at->getTimestamp())->toBe($originalEpoch);

        $item = Item::factory()->create();
        $location = Location::factory()->create();
        $manager = app(StockManager::class);
        $receipt = $manager->receive(
            $item, $location, '99999999999999.9999',
            occurredAt: new DateTimeImmutable('2026-11-18T17:30:00+08:00'),
        );
        expect($receipt->fresh()->occurred_at->format('Y-m-d H:i:sP'))->toBe('2026-11-18 09:30:00+00:00');
        expect($item->stockBalances()->sole()->quantity)->toBe('99999999999999.9999');
        expect(fn () => $manager->receive($item, $location, '0.0001'))->toThrow(ValidationException::class);
        expect($item->stockBalances()->sole()->quantity)->toBe('99999999999999.9999');
        $issue = $manager->issue($item, $location, '0.0001');
        expect($issue->fresh()->quantity_after)->toBe('99999999999999.9998');
        $adjustment = $manager->adjust($item, $location, '-99999999999999.9998', reason: 'Physical count');
        expect($adjustment->fresh()->quantity_after)->toBe('0.0000');
        expect($item->stockMovements()->orderBy('id')->pluck('quantity_change')->all())
            ->toBe(['99999999999999.9999', '-0.0001', '-99999999999999.9998']);

        $user = User::factory()->create();
        $user->fresh()->delete();
        expect(fn () => $manager->receive($item, $location, '1', user: $user))->toThrow(QueryException::class);
        expect($item->stockBalances()->sole()->quantity)->toBe('0.0000');
        expect($item->stockMovements()->count())->toBe(3);

        $concurrentItem = Item::factory()->create();
        $concurrentLocation = Location::factory()->create();
        $parentConnectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $contend = function (string $operation, string $parentQuantity, string $workerQuantity, int $expectedExit) use (
            $database, $manager, $concurrentItem, $concurrentLocation, $parentConnectionId, $admin, &$workers,
        ): string {
            DB::beginTransaction();
            $manager->{$operation}($concurrentItem, $concurrentLocation, $parentQuantity);
            $worker = new Process([
                PHP_BINARY, base_path('tests/MariaDbStockWorker.php'), $database,
                (string) $concurrentItem->id, (string) $concurrentLocation->id, $operation, $workerQuantity,
            ], base_path(), timeout: 20);
            $workers[] = $worker;
            $worker->start();
            $readyOutput = '';
            $ready = $worker->waitUntil(function (string $type, string $chunk) use (&$readyOutput): bool {
                $readyOutput .= $chunk;

                return preg_match('/READY:([0-9]+)/', $readyOutput) === 1;
            });
            expect($ready)->toBeTrue();
            preg_match('/READY:([0-9]+)/', $readyOutput, $matches);
            $workerConnectionId = (int) $matches[1];
            expect($workerConnectionId)->not->toBe($parentConnectionId);
            $deadline = microtime(true) + 5;
            $waiting = false;

            do {
                $waiting = (int) $admin->selectOne(
                    "SELECT COUNT(*) AS waiting FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id = ? AND trx_state = 'LOCK WAIT'",
                    [$workerConnectionId],
                )->waiting === 1;

                if (! $waiting) {
                    usleep(50000);
                }
            } while (! $waiting && microtime(true) < $deadline);

            expect($waiting)->toBeTrue();
            DB::commit();
            $exitCode = $worker->wait();
            expect($exitCode)->toBe($expectedExit);

            return $worker->getOutput();
        };

        $firstReceiptResult = $contend('receive', '1.0001', '0.0002', 0);
        expect($firstReceiptResult)->toContain('"status":"recorded"');
        expect($concurrentItem->stockBalances()->sole()->quantity)->toBe('1.0003');
        expect($concurrentItem->stockMovements()->count())->toBe(2);
        expect($concurrentItem->stockMovements()->orderBy('id')->pluck('quantity_before')->all())->toBe(['0.0000', '1.0001']);

        $issueResult = $contend('issue', '0.7500', '0.7500', 2);
        expect($issueResult)->toContain('"status":"rejected"')->toContain('Insufficient stock');
        expect($concurrentItem->stockBalances()->sole()->quantity)->toBe('0.2503');
        expect($concurrentItem->stockMovements()->count())->toBe(3);
        expect($concurrentItem->stockMovements()->latest('id')->first()->quantity_after)->toBe('0.2503');
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }

        if (DB::connection('mysql')->transactionLevel() > 0) {
            DB::connection('mysql')->rollBack(0);
        }

        DB::purge('mysql');

        if ($created && preg_match('/\\Am2_stock_verify_[a-f0-9]{24}\\z/', $database)) {
            $admin->statement('DROP DATABASE `'.$database.'`');
        }

        DB::purge('admin');
    }
})->group('mariadb');
