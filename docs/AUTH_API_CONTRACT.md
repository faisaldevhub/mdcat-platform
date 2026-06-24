# Auth API Contract

> [!NOTE]
> This document defines the authentication contract between the WordPress REST API (backend) and the Next.js frontend. Both teams must follow this spec exactly.

---

## Base URL

```
POST /wp-json/mdcat/v1/auth/login
POST /wp-json/mdcat/v1/auth/refresh
POST /wp-json/mdcat/v1/auth/logout
GET  /wp-json/mdcat/v1/auth/me
```

---

## 1. Login

### Request

```
POST /wp-json/mdcat/v1/auth/login
Content-Type: application/json
```

```json
{
    "email": "student@example.com",
    "password": "their_password"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `email` | string | Yes | Must be a valid email address |
| `password` | string | Yes | Raw password (not hashed) |

### Success Response — `200 OK`

```json
{
    "success": true,
    "message": "Login successful.",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "user": {
            "id": 42,
            "display_name": "Ahmed Khan",
            "email": "student@example.com",
            "role": "subscriber",
            "avatar_url": "https://secure.gravatar.com/avatar/abc123?s=96&d=mm&r=g"
        }
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `access_token` | string | JWT for API requests. Send in `Authorization: Bearer <token>` header. |
| `refresh_token` | string | JWT for obtaining new access tokens. Longer lifetime. |
| `token_type` | string | Always `"Bearer"`. Tells the client how to use the token. |
| `expires_in` | int | Access token lifetime in seconds (default 86400 = 24h). |
| `user.id` | int | WordPress user ID. |
| `user.display_name` | string | Student's display name. |
| `user.email` | string | Student's email address. |
| `user.role` | string | WordPress role (typically `"subscriber"` for students). |
| `user.avatar_url` | string | Gravatar URL (96px). |

### Error Responses

**Missing credentials — `400`**
```json
{
    "success": false,
    "code": "missing_credentials",
    "message": "Email and password are required.",
    "errors": {}
}
```

**Invalid credentials — `401`**
```json
{
    "success": false,
    "code": "incorrect_password",
    "message": "The password you entered is incorrect.",
    "errors": {}
}
```

**Suspended account — `403`**
```json
{
    "success": false,
    "code": "account_suspended",
    "message": "Your account has been suspended. Please contact the administrator.",
    "errors": {}
}
```

**Rate limited — `429`**
```json
{
    "success": false,
    "code": "too_many_attempts",
    "message": "Too many login attempts. Please try again later.",
    "errors": {}
}
```

---

## 2. Refresh Token

### Request

```
POST /wp-json/mdcat/v1/auth/refresh
Content-Type: application/json
```

```json
{
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `refresh_token` | string | Yes | The refresh token from the login response |

### Success Response — `200 OK`

```json
{
    "success": true,
    "message": "Token refreshed successfully.",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..._new",
        "token_type": "Bearer",
        "expires_in": 86400,
        "user_id": 42
    }
}
```

> [!NOTE]
> The refresh endpoint returns a **new access token only**. The original refresh token remains valid until its own expiry (30 days). The frontend does not need to store a new refresh token.

### Error Responses

**Missing token — `400`**
```json
{
    "success": false,
    "code": "missing_token",
    "message": "Refresh token is required.",
    "errors": {}
}
```

**Expired refresh token — `401`**
```json
{
    "success": false,
    "code": "token_expired",
    "message": "Token has expired.",
    "errors": {}
}
```

**Access token used as refresh — `401`**
```json
{
    "success": false,
    "code": "token_type_mismatch",
    "message": "Expected a refresh token.",
    "errors": {}
}
```

**Suspended account — `403`**
```json
{
    "success": false,
    "code": "account_suspended",
    "message": "Your account has been suspended. Please contact the administrator.",
    "errors": {}
}
```

---

## 3. Get Current User

### Request

```
GET /wp-json/mdcat/v1/auth/me
Authorization: Bearer <access_token>
```

No request body.

### Success Response — `200 OK`

```json
{
    "success": true,
    "message": "User profile loaded.",
    "data": {
        "id": 42,
        "display_name": "Ahmed Khan",
        "email": "student@example.com",
        "role": "subscriber",
        "avatar_url": "https://secure.gravatar.com/avatar/abc123?s=96&d=mm&r=g",
        "registered_at": "2026-01-15 10:30:00"
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | WordPress user ID |
| `display_name` | string | Student's display name |
| `email` | string | Student's email address |
| `role` | string | WordPress role |
| `avatar_url` | string | Gravatar URL (96px) |
| `registered_at` | string | Account creation date (MySQL format) |

### Error Responses

**Missing token — `401`**
```json
{
    "success": false,
    "code": "missing_token",
    "message": "Authorization header is required.",
    "errors": {}
}
```

**Expired token — `401`**
```json
{
    "success": false,
    "code": "token_expired",
    "message": "Token has expired.",
    "errors": {}
}
```

---

## 4. Logout

### Request

```
POST /wp-json/mdcat/v1/auth/logout
Authorization: Bearer <access_token>
```

No request body.

### Success Response — `200 OK`

```json
{
    "success": true,
    "message": "Logged out successfully.",
    "data": null
}
```

> [!NOTE]
> JWT is stateless — the server does not maintain a session or token blocklist. Logout is handled client-side by discarding the stored tokens. This endpoint exists so the frontend has a consistent API call to represent the logout action, and to allow future server-side token invalidation if needed.

### Error Responses

**Missing token — `401`**
```json
{
    "success": false,
    "code": "missing_token",
    "message": "Authorization header is required.",
    "errors": {}
}
```

---

## Frontend Token Usage

### Storing tokens

```
Access token  → In-memory (React state/context). Cleared on page refresh.
Refresh token → httpOnly cookie or secure localStorage. Persists across refreshes.
```

### Sending authenticated requests

```
GET /wp-json/mdcat/v1/dashboard
Authorization: Bearer <access_token>
```

### Refresh flow

```
1. API call returns 401 with code "token_expired"
2. Frontend calls POST /auth/refresh with stored refresh_token
3. If 200 → store new access_token, retry the original request
4. If 401 → refresh token also expired → redirect to login
5. If 403 → account suspended → show suspension message
```

---

## User Object — Exposed Fields Only

The `user` object in auth responses is intentionally limited to **frontend-safe fields**. The following WordPress user properties are **never exposed**:

| Excluded Field | Reason |
|---------------|--------|
| `user_pass` | Password hash — security risk |
| `user_activation_key` | Internal activation mechanism |
| `user_status` | WordPress internal status flag |
| `user_nicename` | URL slug — not needed by frontend |
| `user_url` | Personal URL — not relevant |
| `allcaps` | Full capability map — internal |
| `caps` | Role-capability mapping — internal |
| `filter` | WordPress filter state — internal |
