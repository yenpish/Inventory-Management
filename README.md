<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Fresh local clone

Use your own computer and database server. Never point a clone at the working Inventory or Helpdesk databases.

Requirements: 64-bit PHP 8.4 with Composer 2, Laravel's required PHP extensions, PDO MySQL for the application and PDO SQLite for tests; Node.js 22.12+ (or another version supported by the locked Vite package), npm, and MySQL/MariaDB with InnoDB. This project is developed with Laravel 13 and MariaDB 10.4. Install the versions in `composer.lock` and `package-lock.json` rather than updating dependencies.

For a **new clone only**, in PowerShell:

```powershell
git clone https://github.com/yenpish/Inventory-Management.git inventory-management
cd inventory-management
composer install
Copy-Item .env.example .env
php artisan key:generate --no-interaction
npm ci
```

Create an empty `inventory_management` database on **your own local server**. The administrator commands deliberately require that database name; it is separate from our database because the server is your own. Configure your private `.env` with `DB_CONNECTION=mysql`, your own host, port, database user and password, `APP_ENV=local`, `APP_NAME="Inventory Management"`, and an `APP_URL` matching your local site. Keep the generated application key unique and leave `SESSION_DOMAIN=null` for host-only cookies. Never copy another developer's `.env`, accounts or data.

Before creating tables, clear any stale configuration and verify the intended server and database:

```shell
php artisan config:clear
php artisan config:show database.default
php artisan config:show database.connections.mysql.host
php artisan config:show database.connections.mysql.port
php artisan config:show database.connections.mysql.database
```

Resolve any connection-URL override before proceeding. After confirming your own server and empty database:

```shell
php artisan migrate --no-interaction
php artisan app:create-admin
npm run build
php artisan test --compact
php artisan serve
```

The account command prompts privately for your own credentials. There are no default credentials or demo records. Open the address printed by Artisan, or use a separately configured Herd site with the matching `APP_URL`. Keep the database service running: the default session and cache drivers use it. The compiled build is sufficient for this milestone; `npm run dev` is optional during frontend work. Do not use `composer run setup` as a shortcut: it regenerates the key and runs migrations before you can verify the target. No reset commands or seeders are needed.

The normal tests run migrations against SQLite memory, including the administrator and submission-key migrations. This verifies a clean SQLite install; MariaDB schema conversion and locking have the separate opt-in checks documented below. The UTC correction migration is forward-only, so do not expect a full migration rollback to succeed.

## Inventory demonstration: catalog and stock entry

This update adds administrator-only create/edit forms for categories, locations and items, plus Receive Stock and Issue Stock. Existing accounts default to read-only. Archived records remain preserved and cannot be edited through these forms; archived category/parent references may be retained on existing records. Codes remain unique, including archived records. Item type and unit cannot change after any stock position or movement exists.

### Manual setup (working database is not changed automatically)

From this project directory, first inspect the configured target:

```shell
php artisan config:show database.default
php artisan config:show database.connections.mysql.database
php artisan migrate:status
```

Proceed only when the connection is the intended MySQL/MariaDB connection and its database is exactly `inventory_management`. If configuration uses a connection URL or cached values, resolve that before migrating. Never substitute the Helpdesk database. Apply normal forward migrations only:

```shell
php artisan migrate --no-interaction
php artisan app:promote-admin
npm run build
```

The two new migrations add a default-false `users.is_admin` flag and a nullable unique `stock_movements.submission_key`. Existing rows and all applied migrations remain intact. The promotion command asks for the existing account email and explicit confirmation, checks the actual selected database, refuses noninteractive elevation, and leaves the password unchanged. It does not create a new account. For a new trusted administrator, `app:create-admin` now explicitly grants administrator access after private password prompts. No credentials belong in source control.

### Manual demonstration

1. Sign in with the promoted account. The top bar should say **Administrator**.
2. Open Categories, choose **Add category**, and save a category.
3. Open Locations, choose **Add location**, and save a location. Parent location is optional.
4. Open Items, choose **Add item**, select the category, choose **Stock**, set a unit such as box, and leave it active. Edit links on each listing allow corrections.
5. Open **Current Stock**, choose **Receive Stock**, select that item/location, and receive `10.5000` with a reference.
6. Choose **Issue Stock** for `2.2500`. Current Stock should show `8.25`; Stock History should show both entries, signed quantities, before/after values, date, user and reference. Display quantities omit trailing fractional zeros; stored values retain four decimal places.
7. Try issuing `9`: the form should explain insufficient stock, with no balance/history change. Invalid/zero quantities and asset, inactive or archived records are rejected server-side.
8. Sign in with an existing unpromoted account to demonstrate read-only access and hidden write controls.

Create working demonstration records manually through the UI. Tests create fictional records only in SQLite memory; no demo seeder is included.

### Retry protection and boundaries

Each stock form carries a UUID persisted with its movement in the same StockManager transaction. An identical retry (including browser refresh/resubmission) returns the original movement without changing stock again. Reusing that key with different item, location, actor, operation, quantity, reason or reference is rejected. Save buttons disable during submission, and the redirect follows the POST/redirect/GET pattern. A newly opened form has a new key and represents a new transaction; identical details in separate forms are not automatically treated as duplicates. StockManager calls that omit the optional key remain non-idempotent. Automatically generated occurrence times are not compared on retries; the original recorded time is retained.

Authorization is enforced by the `manage-inventory` gate on write routes and Form Requests. Standard session authentication and CSRF protections remain in place. No role package, additional dependencies or framework changes were added. No deletion, restore, adjustments UI, account-management UI or asset transaction workflow is included. Catalog edits use normal last-write-wins behavior; existing location hierarchy checks remain model-level and do not provide a new concurrent-reparenting guarantee.

Balance/history atomicity and item-row serialization remain in StockManager. The new unique submission index is the final duplicate safeguard; a conflicting insert rolls back its balance change. The new retry/concurrent-submission paths have SQLite coverage but have not been exercised with concurrent MariaDB connections in this update. Previous M2 MariaDB verification does not establish this new behavior. Direct SQL/bulk updates can still bypass model safeguards.

## M2.5A authentication and inventory workspace

The interface uses Laravel 13's built-in session guard, Blade components, the existing Tailwind CSS 4/Vite stack, and small native JavaScript enhancements for mobile navigation and password visibility. No packages were added. Livewire can be considered when later workflows need persistent server-side interaction; the current read-only pages use shareable GET filters and conventional pagination.

### Initial account and local setup

The original M2.5A schema already contains the users and sessions tables. Apply the newer demonstration migrations above before provisioning accounts. Keep the application's database configured as `inventory_management`. With your existing dependencies installed, run:

```shell
npm run build
php artisan app:create-admin
```

The account command verifies the database target and prompts interactively for a name, email, password, and confirmation. Password prompts disable visible fallback; no password argument or default credential is supported. Use a unique password of at least 12 characters containing upper/lowercase letters, a number, and a symbol, with a maximum of 72 UTF-8 bytes for bcrypt. The command refuses duplicate emails and never overwrites an existing account. Do not put passwords in source files, shell arguments, or seeders. No account is created automatically; the default seeder no longer creates a predictable demo user.

Use your Herd site or `php artisan serve` to open the application. The root page and `/login` show the sign-in form. `/dashboard`, `/items`, `/categories`, `/locations`, `/stock-balances`, and `/movements` require authentication. There is no public registration, password reset, or account-management UI. Existing accounts are read-only unless explicitly promoted. The provisioning command creates trusted administrators; the promotion command grants administrator access to an existing account.

For deployment, use HTTPS, `APP_ENV=production`, and `APP_DEBUG=false`; secure session cookies default to enabled in production. Keep `SESSION_DOMAIN` empty for host-only cookies and do not share session settings or an application key with Helpdesk. The default cookie name is specific to Inventory Management. Login uses generic failure messages, failed-attempt throttling by email/IP plus an IP request limit, session-ID regeneration, and Laravel's web/CSRF middleware. Logout is POST-only and invalidates the session/CSRF token. Protected views send private, no-store cache headers. No authentication or cookie exceptions are added.

### Read-only pages and verification

The dashboard uses actual current catalog counts, all recorded movement counts, retained positive/zero stock positions, recent movements in recording order, and category counts. It does not add quantities across different units. Listings support text search, relevant filters, and 20-row pagination. Catalog pages exclude archived records by default and can include them explicitly; balances and history retain archived item/location references. Quantities render as exact decimal strings. Display dates and movement date filters use `Asia/Kuala_Lumpur`; stored values remain UTC. A date range is inclusive of the selected local calendar days.

The responsive layout includes a desktop sidebar with a compact icon-only mode, keyboard/hover tooltips, a native mobile drawer, an account menu, and reusable form/table/status/empty-state components. The sidebar preference is stored locally in the browser under `inventory.sidebar`; if storage is blocked, the toggle still works but cannot retain the preference after a reload. Tables switch to labelled rows according to their available container width. Stock history keeps quantities, staff, references and reasons available under each row's **Details** disclosure. Search forms work without JavaScript. The later demonstration update above adds protected catalog editing, receiving and issuing. Adjustments remain backend-only.

Visible navigation uses **Current Stock** and **Stock History**. Internal route names, URLs, models and database names remain unchanged. The dashboard no longer contains a promotional banner, and page wording uses short labels and descriptions. Search/filter/pagination tests create fictional records for every listing in SQLite memory only; they never seed the working Inventory database.

```shell
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

The normal suite remains forced to SQLite in memory. Authentication/page tests cover route protection, login failures and throttling, session/logout behavior, real CSRF enforcement, safe account provisioning, escaped output, filtered/paginated results, Malaysia date boundaries, exact quantity display, and read-only browsing. Vite is disabled in PHP tests so they do not depend on generated build artifacts. The opt-in disposable MariaDB verification below remains separate. No test creates users or operational records in the working databases.

## M2 stock backend

Use `App\StockManager::receive()`, `issue()`, and `adjust()` for operational stock writes. Each takes an existing `Item`, `Location`, and quantity, with optional named `user`, `reason`, `reference`, and `occurredAt` arguments. The demonstration Receive/Issue endpoints call this service behind authentication and administrator authorization.

Quantities are integer values or plain decimal **strings**, with at most 14 integer digits and 4 decimal places (`DECIMAL(18,4)`). Floats, scientific notation, zero changes, excess precision, and out-of-range balances are rejected rather than rounded. Arithmetic uses scaled 64-bit integers. Receipts and issues take positive quantities; adjustments take signed deltas and require a nonblank reason. An adjustment records a change, not a replacement absolute balance. Movement dates use explicit UTC `DATETIME` storage at second precision; timezone-aware inputs are normalized to UTC, and omitted dates use the current time. Laravel stays in UTC, and both MySQL/MariaDB connection configurations explicitly set the session to `+00:00`. Frontends can convert UTC to `Asia/Kuala_Lumpur` for display.

The forward migration `2026_11_18_035430_convert_stock_movement_occurred_at_to_utc_datetime` replaces the old TIMESTAMP column without changing the applied create migration. Conversion runs with a UTC session so existing **stored instants** are retained, removes implicit defaults/automatic updates, and restores the previous session timezone afterward. It does not guess the intended wall-clock date of any historically misinterpreted input. The correction is forward-only: rollback is explicitly refused because reverting would narrow the supported date range and risk reintroducing timestamp behavior. MariaDB DDL may lock the table and implicitly commits; plan a maintenance window for a large populated installation.

Balances are unique per item/location. Movements retain their signed change, before/after quantities, occurrence time, user, reason, reference, and recording timestamps. Use correcting movements instead of editing history. Model operations reject movement updates/deletes and balance deletion/reassignment. Item/location relationships include archived records, and restrictive foreign keys preserve historical references, including responsible users. Factories produce test fixtures and are not operational stock entry points.

### Transactions and concurrency

Every `StockManager` operation uses one database transaction with up to three deadlock attempts. It reads and locks the item first, location second, and existing balance third, using `lockForUpdate()`. Eligibility is checked against these freshly read rows, not caller-side model state. The existing item row is the serialization point even for a first receipt with no balance row; the unique item/location key provides another safeguard. Operations for one item are intentionally serialized across locations. The balance write and movement insert commit or roll back together.

These guarantees require transactional InnoDB tables and all stock writers using `StockManager` on the application's default connection. Direct SQL, bulk model updates/deletes, event-suppressed saves, and manual balance edits bypass the application safeguards. This is an application-level append-only history, not a database-enforced audit log. Calls without the optional submission key are not idempotent: repeating a completed call records another movement. Reference text is not a unique idempotency key. The stock UI supplies the key described above. Backdated movements do not recompute prior balances; before/after values describe recording order (movement ID), not occurrence-date order.

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
