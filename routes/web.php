<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', 'cache.headers:no_store;private'])->group(function (): void {
    Route::get('/', [SessionController::class, 'create'])->name('home');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1')->name('login.store');
});

Route::middleware(['auth', 'cache.headers:no_store;private'])->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [InventoryController::class, 'dashboard'])->name('dashboard');
    Route::get('/items', [InventoryController::class, 'items'])->name('items.index');
    Route::get('/categories', [InventoryController::class, 'categories'])->name('categories.index');
    Route::get('/locations', [InventoryController::class, 'locations'])->name('locations.index');
    Route::get('/stock-balances', [InventoryController::class, 'balances'])->name('balances.index');
    Route::get('/movements', [InventoryController::class, 'movements'])->name('movements.index');
});

Route::middleware(['auth', 'cache.headers:no_store;private', 'can:manage-inventory'])->group(function (): void {
    Route::prefix('{kind}')->where(['kind' => 'categories|locations|items'])->group(function (): void {
        Route::get('/create', [CatalogController::class, 'create'])->name('catalog.create');
        Route::post('/', [CatalogController::class, 'store'])->name('catalog.store');
        Route::get('/{record}/edit', [CatalogController::class, 'edit'])->whereNumber('record')->name('catalog.edit');
        Route::put('/{record}', [CatalogController::class, 'update'])->whereNumber('record')->name('catalog.update');
    });
    Route::get('/stock/{operation}', [StockController::class, 'create'])->where('operation', 'receive|issue')->name('stock.create');
    Route::post('/stock/{operation}', [StockController::class, 'store'])->where('operation', 'receive|issue')->name('stock.store');
});
