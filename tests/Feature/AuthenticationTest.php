<?php

use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
    $this->artisan('migrate', ['--database' => 'sqlite', '--no-interaction' => true])->assertExitCode(0);
});

test('guests can see the sign-in page without inventory data', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('Sign in')->assertSee('name="_token"', false)
        ->assertDontSee('Stock positions')->assertDontSee('Catalog items');
});

test('all inventory pages require authentication', function (string $route) {
    $this->get(route($route))->assertRedirectToRoute('login');
})->with(['dashboard', 'items.index', 'categories.index', 'locations.index', 'balances.index', 'movements.index']);

test('successful login regenerates the session and returns to the intended inventory page', function () {
    $user = User::factory()->create(['email' => 'operator@example.test', 'password' => 'Test-Only!12345']);
    $this->get(route('items.index'))->assertRedirectToRoute('login');
    $oldSessionId = session()->getId();

    $this->post(route('login.store'), ['email' => ' OPERATOR@example.test ', 'password' => 'Test-Only!12345'])
        ->assertRedirectToRoute('items.index');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($oldSessionId);
});

test('unknown accounts and invalid passwords have the same generic failure', function (string $email) {
    User::factory()->create(['email' => 'operator@example.test']);

    $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => 'Wrong-Test!12345'])
        ->assertRedirectToRoute('login')
        ->assertSessionHasErrors(['email' => 'The email address or password is incorrect.'])
        ->assertSessionMissing('_old_input.password');

    $this->assertGuest();
})->with(['operator@example.test', 'unknown@example.test']);

test('login validates the required credential fields', function () {
    $this->post(route('login.store'), [])
        ->assertSessionHasErrors(['email' => 'Enter your email address.', 'password' => 'Enter your password.']);

    $this->assertGuest();
});

test('repeated failed logins are throttled even when the next password is correct', function () {
    $this->freezeTime();
    $user = User::factory()->create(['email' => 'limited@example.test', 'password' => 'Test-Only!12345']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Wrong-Test!12345'])
            ->assertSessionHasErrors(['email' => 'The email address or password is incorrect.']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Test-Only!12345'])
        ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Try again in 60 seconds.']);

    $this->assertGuest();
});

test('successful login clears previous credential failures', function () {
    $user = User::factory()->create(['email' => 'operator@example.test', 'password' => 'Test-Only!12345']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Wrong-Test!12345']);
    $key = hash('sha256', $user->email.'|127.0.0.1');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Test-Only!12345'])
        ->assertRedirectToRoute('dashboard');

    expect(RateLimiter::attempts($key))->toBe(0);
});

test('logout invalidates the session and regenerates its CSRF token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['private-state' => 'temporary']);
    $oldToken = session()->token();

    $this->post(route('logout'))->assertRedirectToRoute('login')->assertSessionMissing('private-state');

    $this->assertGuest();
    expect(session()->token())->not->toBe($oldToken);
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
});

test('authenticated users are redirected away from login', function () {
    $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirectToRoute('dashboard');
});

test('registration and GET logout are not exposed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
    $this->get('/logout')->assertStatus(405);
    $this->assertDatabaseCount('users', 0);
});

test('login and logout reject forged requests when CSRF enforcement is enabled', function (string $route) {
    $this->app->bind(PreventRequestForgery::class, function (Application $app): PreventRequestForgery {
        return new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };
    });

    $user = User::factory()->create(['email' => 'operator@example.test', 'password' => 'Test-Only!12345']);

    if ($route === 'logout') {
        $this->actingAs($user);
    }

    $this->post(route($route), ['email' => $user->email, 'password' => 'Test-Only!12345'])->assertStatus(419);
})->with(['login.store', 'logout']);
