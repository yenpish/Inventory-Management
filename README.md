<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## M2 stock backend

Use `App\StockManager::receive()`, `issue()`, and `adjust()` for operational stock writes. Each takes an existing `Item`, `Location`, and quantity, with optional named `user`, `reason`, `reference`, and `occurredAt` arguments. No stock HTTP endpoints are exposed; authentication and authorization must be added before exposing these operations to users.

Quantities are integer values or plain decimal **strings**, with at most 14 integer digits and 4 decimal places (`DECIMAL(18,4)`). Floats, scientific notation, zero changes, excess precision, and out-of-range balances are rejected rather than rounded. Arithmetic uses scaled 64-bit integers. Receipts and issues take positive quantities; adjustments take signed deltas and require a nonblank reason. An adjustment records a change, not a replacement absolute balance. Movement dates use explicit UTC `DATETIME` storage at second precision; timezone-aware inputs are normalized to UTC, and omitted dates use the current time. Laravel stays in UTC, and both MySQL/MariaDB connection configurations explicitly set the session to `+00:00`. Frontends can convert UTC to `Asia/Kuala_Lumpur` for display.

The forward migration `2026_11_18_035430_convert_stock_movement_occurred_at_to_utc_datetime` replaces the old TIMESTAMP column without changing the applied create migration. Conversion runs with a UTC session so existing **stored instants** are retained, removes implicit defaults/automatic updates, and restores the previous session timezone afterward. It does not guess the intended wall-clock date of any historically misinterpreted input. The correction is forward-only: rollback is explicitly refused because reverting would narrow the supported date range and risk reintroducing timestamp behavior. MariaDB DDL may lock the table and implicitly commits; plan a maintenance window for a large populated installation.

Balances are unique per item/location. Movements retain their signed change, before/after quantities, occurrence time, user, reason, reference, and recording timestamps. Use correcting movements instead of editing history. Model operations reject movement updates/deletes and balance deletion/reassignment. Item/location relationships include archived records, and restrictive foreign keys preserve historical references, including responsible users. Factories produce test fixtures and are not operational stock entry points.

### Transactions and concurrency

Every `StockManager` operation uses one database transaction with up to three deadlock attempts. It reads and locks the item first, location second, and existing balance third, using `lockForUpdate()`. Eligibility is checked against these freshly read rows, not caller-side model state. The existing item row is the serialization point even for a first receipt with no balance row; the unique item/location key provides another safeguard. Operations for one item are intentionally serialized across locations. The balance write and movement insert commit or roll back together.

These guarantees require transactional InnoDB tables and all stock writers using `StockManager` on the application's default connection. Direct SQL, bulk model updates/deletes, event-suppressed saves, and manual balance edits bypass the application safeguards. This is an application-level append-only history, not a database-enforced audit log. Calls are not idempotent: repeating a completed call records another movement, and reference text is not a unique idempotency key. Backdated movements do not recompute prior balances; before/after values describe recording order (movement ID), not occurrence-date order.

The normal Pest suite is restricted to SQLite in memory, with no MariaDB connections configured. It verifies behavior, UTC normalization, date preservation, rollback, constraints, and ordinary fractional quantities. SQLite does not implement InnoDB row locks and uses numeric affinity rather than MariaDB's exact decimal storage.

An explicit opt-in MariaDB test lives outside the default Feature/Unit suites. Run it from PowerShell with credentials already configured for a server where creating a disposable database is allowed:

```powershell
$env:M2_MARIADB_VERIFY = '1'
try {
    php artisan test --compact tests/MariaDbStockTest.php
} finally {
    Remove-Item Env:\M2_MARIADB_VERIFY
}
```

The test generates its own `m2_stock_verify_<24 hex characters>` database, clears connection URLs, checks the actual selected database, and uses a server connection without a selected database for provisioning. It never accepts an existing database name and drops only the database successfully created by that run. It uses normal migrations, never database reset commands. Workers refuse any name outside the disposable pattern. Root-equivalent test-server privileges are needed for database provisioning and observing InnoDB lock waits; credentials are not embedded in test files. If the test process is forcibly killed, its disposable database may require manual cleanup; never substitute a working database.

The integration test verifies populated legacy timestamp conversion (including a prior UTC+08 session), timezone restoration/reconnection, absence of automatic datetime updates, full decimal boundaries, rollback, and receipt/issue/adjustment history. Two separate PHP processes exercise first receipts and competing issues, explicitly observing the second connection in an InnoDB lock wait before releasing the first. These scenarios passed locally on MariaDB 10.4.32. Further load and failure testing can cover:

- Exercise concurrent operations across locations, eligibility changes, and deadlock retries; verify successful calls have exactly one movement and failed calls leave no partial changes.
- Exercise sustained contention, interrupted clients, and different MariaDB versions/isolation settings on a dedicated test server.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
