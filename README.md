# Veyron

**Veyron** (composer: `mithc-borty/veyron`) is a dependency-free custom PHP framework and REST API application. It is built from scratch with native PHP 8, PDO and MySQL — **no frameworks, no ORM, no third-party runtime packages** — while remaining fully Composer-ready for when packages are needed.

The repository contains two things at once:

1. **A small, readable framework** (`app/Core/*`) — routing, autoloading, request/response handling, validation, a PDO wrapper, authentication (session + JWT), logging, views and error handling.
2. **A working application** built on that framework — a JSON REST API (`/api/v1/...`) with user management and token/session authentication, plus a few server-rendered pages (`/`, `/login`, `/dashboard`).

The empty `src/` directory is reserved for extracting the framework into a reusable library under the `MithcBorty\Veyron\` namespace (already mapped in `composer.json`).

---

## Features

- **Zero runtime dependencies** — `composer.json` has an empty `require` section; the framework runs on PHP alone.
- **Front controller architecture** — every request (API and web) enters through `index.php`.
- **Two-layer PSR-4 autoloading** — Composer for vendor packages and the framework namespace, a minimal custom autoloader for the `App\` namespace.
- **JSON-first API** with a consistent response envelope and centralized error handling (no stack traces or SQL ever leak to the client).
- **Dual authentication** — JWT bearer tokens (HS256, implemented natively) or legacy opaque tokens for API clients; hardened PHP session cookies for browsers, with CSRF protection on writes.
- **Thin controllers / service layer** — business logic lives in `app/Services`, data access in `app/Models`, every query a prepared statement.
- **Lightweight validation** with `required`, `email`, `unique`, `exists`, `confirmed` and more.
- **Native PHP views** with dot notation, layouts and automatic escaping — no template engine.
- **File logging** to `storage/logs/` with automatic redaction of passwords, tokens and secrets.
- **Deployment-independent routing** — works at a domain root or in an Apache subdirectory without changing a single route.

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ (developed against 8.4) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8 / MariaDB 10+, `utf8mb4` |
| Apache | 2.4 with `mod_rewrite` and `AllowOverride All` (or the PHP built-in server for development) |

---

## Quick start

```bash
# 1. Get the code and generate the Composer autoloader
git clone <repository-url> veyron
cd veyron
composer dump-autoload          # vendor/ is git-ignored; this regenerates vendor/autoload.php

# 2. Configure the environment
cp .env.example .env            # then edit the DB_* credentials
#   For JWT authentication, set JWT_SECRET (generate one with):
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

# 3. Create the database schema (idempotent SQL files — run in order)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS javascript CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root javascript < database/migrations/001_create_users_table.sql
mysql -u root javascript < database/migrations/002_create_api_tokens_table.sql

# 4. Run it
php -S 127.0.0.1:8080 index.php      # development (built-in server, index.php as router script)
#   ...or point Apache at the folder — see "Deployment" below.

# 5. Verify
curl http://127.0.0.1:8080/api/v1/health
# {"success":true,"message":"API is healthy"}
```

---

## Directory structure

```
veyron/
├── index.php                  # Front controller — the only web entry point
├── .htaccess                  # Rewrites every request to index.php + hardening rules
├── .env                       # Local configuration (never committed)
├── .env.example               # Template for .env
├── composer.json              # PSR-4 mapping: MithcBorty\Veyron\ => src/
├── vendor/                    # Composer autoloader (generated; git-ignored)
│
├── app/                       # The application — namespace App\
│   ├── Config/                #   App\Config      — .env loader (App) and DB credentials (Database)
│   ├── Controllers/           #   App\Controllers — thin HTTP layer (auth, users, session, web pages)
│   ├── Core/                  #   App\Core        — the framework itself (see table below)
│   ├── Helpers/               #   helpers.php     — global functions (base_path, env, e, csrf_field, ...)
│   ├── Middleware/            #   App\Middleware  — AuthMiddleware, RoleMiddleware (static handlers)
│   ├── Models/                #   App\Models      — data access: User, ApiToken (prepared statements only)
│   └── Services/              #   App\Services    — business logic: AuthService, UserService
│
├── database/
│   └── migrations/            # Plain, idempotent SQL files (executed manually, in order)
│
├── docs/
│   ├── API.md                 # Full API reference (envelope, auth, endpoints, curl examples)
│   └── api/PHP-REST-API.postman_collection.json
│
├── resources/
│   └── views/                 # Native PHP templates (home, login, dashboard, layouts/app)
│
├── routes/
│   ├── api.php                # Registers every /api/v1/... route
│   └── web.php                # Registers the server-rendered pages
│
├── src/                       # Empty — reserved for the reusable MithcBorty\Veyron\ framework
│
└── storage/
    └── logs/                  # app-YYYY-MM-DD.log files (rotated per day)
```

### The framework core (`app/Core/`)

| Class | Responsibility |
|---|---|
| `App\Core\Autoloader` | Minimal PSR-4 autoloader: `App\Core\Router` → `app/Core/Router.php`. Registered once from `index.php`. |
| `App\Core\Router` | GET/POST/PUT/PATCH/DELETE routing with `{param}` placeholders, middleware and deployment-independent base paths. |
| `App\Core\Request` | The incoming request: method, path, query, headers, cookies, JSON body, route params. |
| `App\Core\Response` | Centralised JSON (`success`/`error`/`collection`), HTML and redirect responses with a standard envelope. |
| `App\Core\Database` | Lazy PDO singleton wrapper — models never touch PDO directly and every query is a prepared statement. |
| `App\Core\Auth` | Authentication: verifies session / JWT / opaque bearer token, issues and revokes tokens, caches the resolved user per request. |
| `App\Core\Jwt` | Native HS256 JWT encode/decode (hash_hmac + base64url) — no JWT package. |
| `App\Core\Session` | Hardened native PHP session wrapper (HttpOnly, strict mode, cookie-only) storing only the user id + CSRF token. |
| `App\Core\Validator` | Rule-string validation (`required|string|min:2`, `unique:users,email`, ...) that fails closed on malformed rules. |
| `App\Core\View` | Native PHP template renderer with dot notation, layouts and output escaping. |
| `App\Core\Logger` | File logger (`storage/logs/app-YYYY-MM-DD.log`) with automatic redaction of sensitive context. |
| `App\Core\ExceptionHandler` | Converts uncaught exceptions and fatal errors into safe JSON responses and log entries. |

---

## Architecture

Veyron follows the **front controller** pattern: `.htaccess` rewrites every request to `index.php`, which wires the application together and dispatches. There is no dependency-injection container and no service locator — core classes are small, static and explicit.

### Request lifecycle

```
HTTP request (GET /api/v1/users/7)
   │   .htaccess: everything is rewritten to index.php
   ▼
index.php  (front controller — bootstrap only, no business logic)
   ├── 1. require vendor/autoload.php        Composer: vendor/ packages + MithcBorty\Veyron\ (src/)
   ├── 2. require app/Core/Autoloader.php    Custom PSR-4: App\ → app/
   │      Autoloader::register('App\\', BASE_PATH . '/app')
   ├── 3. require app/Helpers/helpers.php    Global helper functions
   ├── 4. App\Config\App::load(.env)         Environment configuration
   ├── 5. ExceptionHandler::register()       JSON error responses + logging safety net
   ▼
Request::capture()                           Method, path, query, headers, JSON body
   ▼
Router  (routes/api.php + routes/web.php register all routes)
   ▼
route matched ──► middleware (AuthMiddleware, RoleMiddleware) ──► controller method
   ▼
Service (business logic: AuthService, UserService)
   ▼
Model (User, ApiToken) ──► Database (PDO, prepared statements) ──► MySQL
   ▼
Response ──► JSON envelope (API) or HTML view (web) ──► client
```

Controllers are instantiated per request (`(new AuthController())->login($request)`), receive the `Request` object, delegate to a service, and turn the service's result array into a `Response`. If anything throws, `ExceptionHandler` converts it into a safe JSON error and a log entry.

### Autoloading

Autoloading is split by namespace owner — each prefix has exactly one loader, so there is no overlap or double loading:

| Loader | Owns | Mapping |
|---|---|---|
| **Composer** (`vendor/autoload.php`, generated — never edit it manually) | Third-party `vendor/` packages **and** the framework's own namespace | `MithcBorty\Veyron\` → `src/` |
| **Custom** `App\Core\Autoloader` (kept deliberately) | The application namespace | `App\` → `app/` |

```php
// index.php — bootstrap order matters:
require BASE_PATH . '/vendor/autoload.php';   // 1. Composer first (lowest layer)
require BASE_PATH . '/app/Core/Autoloader.php'; // 2. application PSR-4 loader
Autoloader::register('App\\', BASE_PATH . '/app');
```

Why both? The custom autoloader keeps the framework self-bootable without `vendor/` and stays the single owner of `App\`; Composer handles packages and the future `MithcBorty\Veyron\` namespace. SPL supports multiple autoloaders, so both coexist safely.

Consequences:

- **Adding a class**: create `app/Services/PaymentService.php` with `namespace App\Services;` — it autoloads, nothing to register.
- **Adding a third-party package**: `composer require vendor/package`, then use it — `vendor/autoload.php` already loads it. After changing `composer.json` autoload config, run `composer dump-autoload`.
- **`src/` is empty on purpose**: it is the future home of the standalone framework (`MithcBorty\Veyron\`). Classes placed there are autoloaded via Composer today. The application code itself deliberately stays in `App\` — no namespaces are renamed for branding.

### Configuration

All configuration comes from the `.env` file (`App\Config\App::load()` parses it; real environment variables always win). No credential is ever hard-coded. See `.env.example` for the annotated template.

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application name (shared with templates as `appName`). |
| `APP_ENV` | Environment name, e.g. `local` / `production`. |
| `APP_DEBUG` | `true` enables `Logger::debug()` output. Errors never expose internals regardless of this flag. |
| `APP_URL` | Base URL of the deployment. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CHARSET` | MySQL/MariaDB connection (PDO DSN is built by `App\Config\Database::dsn()`). |
| `API_VERSION` | API version segment, default `v1`. |
| `API_TOKEN_EXPIRY_DAYS` | Lifetime of issued tokens and JWTs (default 30). |
| `JWT_SECRET` | HS256 signing secret. When empty, login falls back to legacy opaque tokens only. |
| `JWT_COOKIE` | Name of the HttpOnly cookie carrying the JWT for browser clients (default `access_token`). |
| `CORS_ALLOWED_ORIGINS` | Comma-separated allowed frontend origins (never `*` together with token auth). |

---

## Routing

Routes are registered in `routes/api.php` (JSON API) and `routes/web.php` (HTML pages). Each file returns a callable that receives the `Router` instance; both register into the same router and their paths never overlap.

```php
// routes/api.php
$auth  = [[AuthMiddleware::class, 'authenticate']];
$admin = [[AuthMiddleware::class, 'authenticate'], [RoleMiddleware::class, 'admin']];

$router->get('/api/v1/health', [HealthController::class, 'index']);
$router->post('/api/v1/users', [UserController::class, 'store'], $admin);
```

- **Methods**: `get()`, `post()`, `put()`, `patch()`, `delete()`.
- **Placeholders**: `/users/{id}` becomes a named regex group; the value is read with `$request->routeParam('id')`.
- **Handler formats**: `[Controller::class, 'method']` (preferred), `'App\Controllers\X@method'`, or a `Closure`.
- **Middleware**: an array of static handlers run before the controller; returning `false` stops the request (the middleware has already sent the error response).
- **Base-path independence**: the router strips the deployment base path (e.g. `/veyron` when the project lives in `htdocs/veyron`), so the same route definitions work at a domain root or in a subdirectory.
- **404 / 405**: unmatched paths return a JSON `404`; a path that exists with a different method returns `405` with an `Allow` header.

---

## HTTP layer: Request & Response

`App\Core\Request` (built via `Request::capture()` from the superglobals) exposes: `method()`, `path()`, `query()/queryParams()`, `header(name)`, `cookies()`, `body()` (JSON decoded once and cached), `rawBody()`, `routeParams()`, `routeParam(name)`.

`App\Core\Response` sends every payload through one envelope:

```json
// success                    // error                    // collection
{ "success": true,            { "success": false,         { "success": true,
  "message": "...",             "message": "...",           "message": "...",
  "data": { ... } }             "errors": { "field": "..." } }  "data": [ ... ],
                                                           "meta": { "page": 1, "per_page": 20,
                                                                     "total": 0, "total_pages": 0 } }
```

Status code constants (`OK`, `CREATED`, `BAD_REQUEST`, `UNAUTHORIZED`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CONFLICT`, `UNPROCESSABLE_ENTITY`, `INTERNAL_SERVER_ERROR`) live on the class, and `Response::html()` / `Response::redirect()` serve the web pages.

---

## Controllers, services and models

The application is strictly layered — controllers stay thin, services hold the business rules, models hold the SQL:

| Layer | Namespace | Rule of thumb |
|---|---|---|
| Controller | `App\Controllers` | Receive `Request`, call a service, build a `Response`. No SQL, no business rules. |
| Service | `App\Services` | Validate input, enforce authorization rules, hash passwords, return plain result arrays. No HTTP. |
| Model | `App\Models` | Data access for one table. Prepared statements only, via `App\Core\Database`. |

- `AuthService` — register, login (shared by API and web form flows), logout, current user. Unknown emails are verified against a dummy hash so response timing does not reveal which emails exist.
- `UserService` — user pagination, create, update (PUT/PATCH), delete. `role`/`status` changes are administrator-only and enforced here, not in the controller.
- `User` model — `users` table access; `toPublic()` / `PUBLIC_COLUMNS` guarantee the password hash never reaches a client. Roles: `admin`, `user`; statuses: `active`, `inactive`.
- `ApiToken` model — `api_tokens` table access; only SHA-256 hashes of tokens are stored, so a plaintext token exists exactly once (the login response) and can never be read back.

## Validation

`App\Core\Validator` validates request input against compact rule strings and returns `field => first error` (empty array = valid):

```php
$errors = Validator::validate($request->body(), [
    'name'     => 'required|string|min:2|max:80',
    'email'    => 'required|string|email|unique:users,email',
    'password' => 'required|string|min:8|confirmed',
]);
```

Supported rules: `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `date`, `regex:pattern`, `in:a,b`, `not_in:a,b`, `min:n`, `max:n`, `same:field`, `different:field`, `confirmed`, `unique:table,column[,ignoreId[,idColumn]]`, `exists:table,column`. Unknown or malformed rules **fail closed** — they produce a validation error and are logged, so a typo can never silently disable validation.

## Database layer

- `App\Core\Database` creates one **lazy PDO connection** (first use) with safe defaults: exceptions on error, associative fetch, emulated prepares off, native types. Helpers: `select()`, `selectOne()`, `execute()`, `lastInsertId()`.
- `App\Config\Database` builds the DSN purely from `.env` values.
- **No query builder, no ORM** — models write plain SQL with positional placeholders.
- Migrations are plain, **idempotent** SQL files under `database/migrations/` (executed manually in filename order). They only `CREATE TABLE IF NOT EXISTS` and never drop or alter existing data:

| File | Table |
|---|---|
| `001_create_users_table.sql` | `users` — id, name, email (unique), password (bcrypt hash), role, status, timestamps |
| `002_create_api_tokens_table.sql` | `api_tokens` — user_id (FK → users, cascade), token_hash (unique, SHA-256 hex), expires_at, created_at, last_used_at |

---

## Authentication & security

`App\Core\Auth` authenticates a request with the **first method that succeeds**:

1. **Session cookie** (browsers) — the logged-in user id from the PHP session; the user must still exist and be active.
2. **JWT bearer token** (preferred for API clients) — `Authorization: Bearer <jwt>`; HS256 signature, expiry and `jti` verified. Every JWT id is also recorded (hashed) in `api_tokens`, so `POST /auth/logout` revokes the JWT immediately.
3. **Legacy opaque token** — `Authorization: Bearer <64-hex-token>`; kept for backward compatibility.

Security properties baked into the framework:

- Passwords are stored with `password_hash()` and never returned by any endpoint or log line.
- Tokens (opaque **and** JWT `jti`) are stored only as SHA-256 hashes; the plaintext token is sent once, in the login response.
- Login responses and expired/revoked/unknown credentials are indistinguishable in timing (`DUMMY_HASH` verification) and return `401`.
- The session cookie is hardened: `HttpOnly`, `use_strict_mode`, cookie-only, `SameSite=Lax`, and it stores only the user id + CSRF token — never passwords or bearer tokens.
- **CSRF**: session-authenticated writes (POST/PUT/PATCH/DELETE) require the `X-CSRF-TOKEN` header (`AuthMiddleware`); bearer-token requests are exempt. `GET /api/v1/auth/csrf` issues the token; web forms embed it with the `csrf_field()` helper.
- Role-based authorization via `RoleMiddleware::admin()`; finer "self or admin" rules are enforced inside `UserService`.
- `last_used_at` is updated on every authenticated request (`ApiToken::touch`); logout deletes the stored hash, and every token of a user can be revoked at once (`Auth::revokeAllForUser`).

## Views and helpers

Templates are plain PHP files under `resources/views/`, addressed with dot notation (`home`, `layouts.app`) and rendered with an optional layout — the template output becomes `$content` inside the layout:

```php
Response::html(view('home', ['title' => 'Welcome'], 'layouts.app'));
```

Always escape output with `e()`; the only exception is `$content`, which is trusted HTML produced by another template of this application. `View::share(['appName' => App::name()])` makes data available to every template.

Global helpers (`app/Helpers/helpers.php`, all guarded with `function_exists`):

| Helper | Purpose |
|---|---|
| `base_path('storage/logs')` | Absolute path inside the project root. |
| `storage_path('logs')` | Absolute path inside `storage/`. |
| `env('KEY', $default)` | Read a value from the environment / `.env`. |
| `app_debug()` | Whether `APP_DEBUG=true`. |
| `url('/login')` | Prefix a path with the deployment base path. |
| `view('home', $data, 'layouts.app')` | Render a template (optionally inside a layout). |
| `e($value)` | HTML-escape a value for safe output. |
| `csrf_token()` / `csrf_field()` | Session CSRF token / hidden form input. |
| `session_has_user()` | Whether the session belongs to a logged-in user (for templates). |

## Error handling and logging

- `index.php` disables `display_errors` and enables `log_errors` — raw PHP errors never reach the client.
- `App\Core\ExceptionHandler` registers `set_exception_handler` + a shutdown handler for fatals: every unhandled error becomes a safe JSON response (`JsonException` → `400 Invalid JSON request body`, anything else → `500 Internal server error`) and a log entry. Stack traces, SQL, paths and credentials stay server-side.
- `App\Core\Logger` writes `storage/logs/app-YYYY-MM-DD.log` with levels `debug` (only when `APP_DEBUG=true`), `info`, `warning`, `error`. Context keys containing `password`, `token`, `secret`, `authorization` or `credential` are redacted, messages/context are length-capped, and a write failure falls back to `error_log()` instead of breaking the request.

---

## HTTP API

Base path: `/api/v1` (configurable via `API_VERSION`). Full reference with request/response examples: **[docs/API.md](docs/API.md)**; a ready-made Postman collection ships in `docs/api/`.

| Method | Endpoint | Auth | Handler |
|---|---|---|---|
| GET | `/api/v1/health` | public | `HealthController@index` |
| POST | `/api/v1/auth/register` | public | `AuthController@register` |
| POST | `/api/v1/auth/login` | public | `AuthController@login` |
| GET | `/api/v1/auth/csrf` | public | `SessionController@csrf` |
| GET | `/api/v1/session/csrf` | public (alias) | `SessionController@csrf` |
| POST | `/api/v1/auth/logout` | any authenticated | `AuthController@logout` |
| GET | `/api/v1/auth/me` | any authenticated | `AuthController@me` |
| GET | `/api/v1/users` | any authenticated | `UserController@index` |
| GET | `/api/v1/users/{id}` | any authenticated | `UserController@show` |
| POST | `/api/v1/users` | admin only | `UserController@store` |
| PUT | `/api/v1/users/{id}` | self or admin | `UserController@update` |
| PATCH | `/api/v1/users/{id}` | self or admin | `UserController@patch` |
| DELETE | `/api/v1/users/{id}` | self or admin | `UserController@destroy` |

Quick example:

```bash
# Login (returns token, csrf_token and sets the session cookie)
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Password123!"}'

# Authenticated request
curl http://localhost:8080/api/v1/auth/me \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

## Web pages (server-rendered)

| Method | Endpoint | Handler | Notes |
|---|---|---|---|
| GET | `/` | `WebController@home` | Public landing page. |
| GET | `/login` | `WebController@showLogin` | Redirects to `/dashboard` when already logged in. |
| POST | `/login` | `WebController@login` | Form login (session + CSRF token). |
| GET | `/dashboard` | `WebController@dashboard` | Session-authenticated page. |
| POST | `/logout` | `WebController@logout` | Ends the browser session. |

---

## Deployment

### Apache (production)

The project ships with an `.htaccess` that requires `mod_rewrite` and `AllowOverride All` (or at least `FileInfo`). Both layouts work without touching the code:

```apache
# Option A — the project folder is the document root
#   http://veyron.local/api/v1/health
DocumentRoot "C:/Apache24/htdocs/veyron"

# Option B — the project lives in a subdirectory of htdocs
#   http://localhost/veyron/api/v1/health
```

The front controller derives the base path from `SCRIPT_NAME`, and the router strips it before matching, so route definitions never change between the two.

The `.htaccess` also hardens the deployment:

- rewrites every request to `index.php` (real files/directories stay accessible);
- forwards the `Authorization` header to PHP (needed for Bearer tokens);
- forbids direct access to `app/`, `routes/`, `database/`, `storage/`, `docs/`, `resources/`;
- denies all dotfiles (`.env` is never served) and disables directory listings.

### PHP built-in server (development)

```bash
php -S 127.0.0.1:8080 index.php
```

Using `index.php` as the router script makes every request hit the front controller, mirroring the Apache rewrite.

---

## Code conventions

- `declare(strict_types=1);` at the top of every PHP file.
- `final` classes; static-only classes use private constructors (the core is intentionally static and stateless-per-request).
- No dependency-injection container, no ORM, no query builder, no template engine, no external packages — everything is readable top-to-bottom.
- Internal details (SQL, stack traces, paths, credentials) are logged, never responded.
- All SQL uses prepared statements; identifiers in dynamic rules are restricted to `[A-Za-z0-9_]` and quoted.

## Extending the framework

1. **New API endpoint** — add a controller method (`app/Controllers/`), a service method when logic is non-trivial, then register the route in `routes/api.php`. Nothing else to wire: the class autoloads.
2. **New table** — add an idempotent `00N_*.sql` migration in `database/migrations/` and a matching model in `app/Models/` that uses `App\Core\Database`.
3. **New middleware** — add a static class in `app/Middleware/` returning `false` to reject (it must send its own response), then list it in the route's middleware array.
4. **New helper** — add a `function_exists`-guarded function in `app/Helpers/helpers.php`.
5. **Third-party library** — `composer require vendor/package`; it becomes autoloadable immediately through `vendor/autoload.php`.
6. **Framework extraction** — the empty `src/` directory plus the `MithcBorty\Veyron\` PSR-4 mapping in `composer.json` is the designated home for the reusable framework core; classes placed there autoload without further configuration.

## Documentation

- [`docs/API.md`](docs/API.md) — complete API reference: response envelope, authentication modes, CSRF, every endpoint with curl examples and status codes.
- [`docs/api/PHP-REST-API.postman_collection.json`](docs/api/PHP-REST-API.postman_collection.json) — Postman collection for the API.
- [`.env.example`](.env.example) — annotated environment configuration template.







