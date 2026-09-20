# PHP REST API — API Reference

Native PHP 8.2+ REST API (PDO, no frameworks, no dependencies).

## Base URL

```
http://javascript.local/api/v1
```

## Response envelope

Success:

```json
{ "success": true, "message": "User retrieved successfully", "data": {} }
```

Collection:

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [],
  "meta": { "page": 1, "per_page": 20, "total": 0, "total_pages": 0 }
}
```

Error:

```json
{ "success": false, "message": "Validation failed", "errors": { "email": "The email field is required." } }
```

Internal details (SQL, stack traces, paths, credentials) are never returned; they are written to `storage/logs/`.

## Authentication

Two modes, both built in:

1. **Bearer token** (preferred for API clients) — a **JWT** (HS256, signed with `JWT_SECRET`). When `JWT_SECRET` is empty, login falls back to a legacy opaque token; both use the same `Authorization` header.
2. **Session** (for browsers) — PHP native session cookie (`HttpOnly`/`SameSite=Lax`) issued at login. Write methods (POST/PUT/PATCH/DELETE) then require the session CSRF token in the `X-CSRF-TOKEN` header.

```
Authorization: Bearer TOKEN
Accept: application/json
```

* `POST /auth/login` returns the JWT **once** (`data.token`), the session **CSRF token** (`data.csrf_token`) and logs the browser in via cookie in the same response.
* JWT claims: `iss=php-rest-api`, `sub` (user id), `iat`, `nbf`, `exp`, `jti` (unique). Revocation is server-side: every JWT `jti` is also recorded in `api_tokens` (hash only), so `POST /auth/logout` invalidates it immediately.
* Tokens/JWTs expire after `API_TOKEN_EXPIRY_DAYS` days (default 30).
* `last_used_at` is updated on each authenticated request.
* A credential of an `inactive` user, or an expired/revoked/unknown token, is rejected with `401`.
* Passwords are stored with `password_hash()` and are never returned by any endpoint.

### CSRF (session mode)

* `GET /auth/csrf` (public) returns the caller's CSRF token and issues an anonymous session cookie for pre-login forms.
* Login **rotates** the CSRF token; always use the one from the latest login response.
* Send it on every session-authenticated write: `X-CSRF-TOKEN: <token>` (missing/invalid → `403`). Bearer-token requests never need it.

## Health

| Method | Endpoint | Auth |
|---|---|---|
| GET | `/health` | public |

```bash
curl http://javascript.local/api/v1/health
```

`200 OK`

```json
{ "success": true, "message": "API is healthy" }
```

## Authentication endpoints

| Method | Endpoint | Auth |
|---|---|---|
| POST | `/auth/register` | public |
| POST | `/auth/login` | public |
| POST | `/auth/logout` | bearer |
| GET | `/auth/me` | bearer |

### POST /auth/register

Body: `name`, `email` (unique), `password` (min 8), `password_confirmation`.
New accounts are always created with `role: user` and `status: active` — `role`/`status` in the body are ignored.

```bash
curl -X POST http://javascript.local/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"Password123!","password_confirmation":"Password123!"}'
```

`201 Created`

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "role": "user",
    "status": "active",
    "created_at": "2026-01-01 12:00:00",
    "updated_at": "2026-01-01 12:00:00"
  }
}
```

Errors: `422` validation (duplicate email → `errors.email`), `400` malformed JSON.

### POST /auth/login

```bash
curl -X POST http://javascript.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Password123!"}'
```

`200 OK`

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { "id": 1, "name": "Test User", "email": "test@example.com", "role": "user", "status": "active" },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.<payload>.<signature>",
    "token_type": "Bearer",
    "expires_at": "2026-01-31 12:00:00",
    "csrf_token": "64 hex characters (session mode)"
  }
}
```

The response also sets the PHP session cookie (`PHPSESSID`) for browser clients.
Errors: `401` wrong credentials (same response for unknown email), `403` inactive account, `422` missing/invalid fields, `400` malformed JSON.

### GET /auth/csrf (session mode helper)

```bash
curl -c cookies.txt http://javascript.local/api/v1/auth/csrf
```

`200 OK` → `{ "success": true, "message": "CSRF token retrieved successfully", "data": { "csrf_token": "..." } }`
Issues/uses an anonymous session cookie; the returned token must be sent as `X-CSRF-TOKEN` on session writes.

### POST /auth/logout

Revokes the bearer token/JWT used in the request **and** destroys the session, whichever (or both) authenticated the call.

```bash
curl -X POST http://javascript.local/api/v1/auth/logout -H "Authorization: Bearer TOKEN"
curl -X POST http://javascript.local/api/v1/auth/logout -b cookies.txt -H "X-CSRF-TOKEN: <session csrf>"
```

`200 OK` → `{"success": true, "message": "Logged out successfully"}`
`401` when nothing authenticated this request (missing/expired/already revoked).

### GET /auth/me

Works with a JWT/opaque bearer token **or** the session cookie.

```bash
curl http://javascript.local/api/v1/auth/me -H "Authorization: Bearer TOKEN"
curl http://javascript.local/api/v1/auth/me -b cookies.txt
```

`200 OK` → `data` = the public user (`id`, `name`, `email`, `role`, `status`, `created_at`, `updated_at`).
`401` when unauthenticated.

## User endpoints

All user endpoints work with a bearer token **or** the session cookie (session writes additionally need `X-CSRF-TOKEN`).

| Method | Endpoint | Who |
|---|---|---|
| GET | `/users?page=1&per_page=20` | any authenticated user |
| GET | `/users/{id}` | any authenticated user |
| POST | `/users` | **administrator only** |
| PUT | `/users/{id}` | the user itself or an administrator |
| PATCH | `/users/{id}` | the user itself or an administrator |
| DELETE | `/users/{id}` | the user itself or an administrator |

Additional rules:

* only an **administrator** may change `role` or `status` (silently ignored for everyone else);
* `email` must stay unique → `422` with `errors.email`;
* deleting a user also deletes its tokens (foreign key `ON DELETE CASCADE`).

### GET /users (paginated)

```bash
curl "http://javascript.local/api/v1/users?page=1&per_page=20" -H "Authorization: Bearer TOKEN"
```

`200 OK`

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    { "id": 1, "name": "Test User", "email": "test@example.com", "role": "user", "status": "active",
      "created_at": "2026-01-01 12:00:00", "updated_at": "2026-01-01 12:00:00" }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 1, "total_pages": 1 }
}
```

Pagination parameters:

| Parameter | Default | Limits |
|---|---|---|
| `page` | 1 | ≥ 1 |
| `per_page` | 20 | 1 … 100 |

Invalid or missing values fall back to the defaults.

### GET /users/{id}

`200 OK` with `data` = the user; `404` `{"success": false, "message": "User not found"}` when the id does not exist.

### POST /users (administrator only)

Body: `name`, `email`, `password`, `password_confirmation`, optional `role` (`admin`|`user`) and `status` (`active`|`inactive`).

```bash
curl -X POST http://javascript.local/api/v1/users \
  -H "Authorization: Bearer ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"API Test User","email":"api-test@example.com","password":"Password123!","password_confirmation":"Password123!","role":"user","status":"active"}'
```

`201 Created` with the created user; `403` for non-administrators; `422` on validation errors.

### PUT /users/{id}

Full update: `name` and `email` are required; `password` is optional (must be sent with `password_confirmation`).

```bash
curl -X PUT http://javascript.local/api/v1/users/1 \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Updated Name","email":"updated@example.com"}'
```

### PATCH /users/{id}

Partial update: send only the fields that change (an empty body is a no-op and returns the current user).

```bash
curl -X PATCH http://javascript.local/api/v1/users/1 \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Patched Name"}'
```

Administrators may also PATCH `role` and `status`.

### DELETE /users/{id}

```bash
curl -X DELETE http://javascript.local/api/v1/users/1 -H "Authorization: Bearer TOKEN"
```

`200 OK` → `{"success": true, "message": "User deleted successfully"}`; `404` when the id does not exist.

## Status codes

| Code | Meaning in this API |
|---|---|
| 200 | OK (read, update, delete, login, logout) |
| 201 | Created (register, user created) |
| 400 | Bad request (malformed JSON body, unknown action) |
| 401 | Unauthenticated (missing/expired/revoked token, wrong credentials, after logout) |
| 403 | Forbidden (admin-only endpoint, inactive account, not the owner) |
| 404 | Route or resource not found |
| 405 | Method not allowed (response includes an `Allow` header) |
| 422 | Validation failed (`errors` object per field, incl. duplicate email) |
| 500 | Internal server error (details only in `storage/logs`) |

## Setup reminder

1. Create the database `javascript`.
2. Run the migrations **in order**:

```bash
mysql -u root javascript < database/migrations/001_create_users_table.sql
mysql -u root javascript < database/migrations/002_create_api_tokens_table.sql
```

3. Copy `.env.example` to `.env` and set `DB_*` / `API_TOKEN_EXPIRY_DAYS`.
4. Create the first administrator (registration always creates a `user`), for example:

```sql
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
```

Testing collection: `docs/api/PHP-REST-API.postman_collection.json`.

