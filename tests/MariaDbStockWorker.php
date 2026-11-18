<?php

use App\Models\Item;
use App\Models\Location;
use App\StockManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';

[$script, $database, $itemId, $locationId, $operation, $quantity] = $argv + array_fill(0, 6, '');

if (! preg_match('/\\Am2_stock_verify_[a-f0-9]{24}\\z/', $database)
    || ! in_array($operation, ['receive', 'issue'], true)
    || ! ctype_digit($itemId) || ! ctype_digit($locationId)) {
    throw new RuntimeException('Refusing an unsafe stock verification target or operation.');
}

foreach ([
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'APP_CONFIG_CACHE' => __DIR__.'/../bootstrap/cache/'.$database.'.php',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.connections.mysql');
unset($connection['url']);
$connection['database'] = $database;
$connection['timezone'] = '+00:00';
DB::purge();
config(['database.default' => 'mysql', 'database.connections' => ['mysql' => $connection]]);

if (DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
    throw new RuntimeException('The worker is not connected to its disposable database.');
}

echo 'READY:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
flush();

try {
    $movement = app(StockManager::class)->{$operation}(
        Item::findOrFail((int) $itemId), Location::findOrFail((int) $locationId), $quantity,
    );

    echo json_encode(['status' => 'recorded', 'movement_id' => $movement->id], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n";
    exit(2);
}
