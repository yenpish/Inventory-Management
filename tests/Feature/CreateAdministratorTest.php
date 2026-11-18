<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
});

test('administrator provisioning hashes credentials without printing the password', function () {
    $password = 'Test-Only!12345';

    $this->artisan('app:create-admin')
        ->expectsQuestion('Name', 'Inventory Operator')
        ->expectsQuestion('Email', ' OPERATOR@example.test ')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->expectsOutputToContain('Inventory account created.')
        ->doesntExpectOutputToContain($password)
        ->assertSuccessful();

    $user = User::sole();
    expect($user->email)->toBe('operator@example.test');
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect($user->password)->not->toBe($password);
});

test('administrator provisioning rejects weak or unconfirmed passwords', function (string $password, string $confirmation) {
    $this->artisan('app:create-admin')
        ->expectsQuestion('Name', 'Inventory Operator')
        ->expectsQuestion('Email', 'operator@example.test')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'weak password' => ['weak', 'weak'],
    'mismatch' => ['Test-Only!12345', 'Different-Test!12345'],
    'bcrypt byte limit' => [str_repeat('Ab1!', 19), str_repeat('Ab1!', 19)],
]);

test('administrator provisioning never overwrites an existing account', function () {
    $user = User::factory()->create(['email' => 'operator@example.test']);
    $originalHash = $user->password;

    $this->artisan('app:create-admin')
        ->expectsQuestion('Name', 'Replacement')
        ->expectsQuestion('Email', $user->email)
        ->expectsQuestion('Password', 'Test-Only!12345')
        ->expectsQuestion('Confirm password', 'Test-Only!12345')
        ->assertFailed();

    expect($user->fresh()->password)->toBe($originalHash);
    $this->assertDatabaseCount('users', 1);
});

test('administrator provisioning refuses noninteractive password input', function () {
    $this->artisan('app:create-admin', ['--no-interaction' => true])
        ->expectsOutputToContain('Run this command interactively')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

test('administrator provisioning refuses another database without opening it', function () {
    DB::purge('sqlite');
    config(['database.connections.sqlite.database' => 'other_application']);

    $this->artisan('app:create-admin')->expectsOutputToContain('Refusing to create an account')->assertFailed();
});

test('the application seeder does not create predictable demo accounts', function () {
    $this->seed();

    $this->assertDatabaseCount('users', 0);
});
