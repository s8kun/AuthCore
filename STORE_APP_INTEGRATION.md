# Store App Integration Guide

This guide explains the simplest supported setup:

- one `auth-core` project running this repository
- one client application called `Store App`
- one project key used by the store app only

This guide does not cover multi-project usage, SSO across multiple apps, or server-to-server secret flows.

Specific framework guides:

- Next.js: [`STORE_APP_NEXTJS_INTEGRATION.md`](./STORE_APP_NEXTJS_INTEGRATION.md)
- Laravel: [`STORE_APP_LARAVEL_INTEGRATION.md`](./STORE_APP_LARAVEL_INTEGRATION.md)

## Goal

`auth-core` handles:

- registration
- login
- logout
- current-user lookup
- refresh tokens
- OTP, password reset, and optional ghost accounts

`Store App` handles:

- storefront logic
- orders
- carts
- customer profiles tied to the auth user by `auth_user_id`

The link between the two systems is:

```text
store_app.customers.auth_user_id = auth_core.project_users.id
```

That means the store app does not need a database foreign key to the auth-core database. It only stores the remote user UUID.

## The Final Shape

You will have only one auth project:

```text
Auth Core
└── Project: Store App
    └── project_users

Store App
└── customers
    └── auth_user_id
```

All store app requests to the auth API use the same `X-Project-Key`.

## URLs In Local Development

- Admin panel: `http://localhost:8000/admin`
- Auth API base URL: `http://localhost:8000/api/v1/auth`
- Generated API docs: `http://localhost:8000/docs/api`

## Step 1: Create The Store App Project In Auth Core

1. Open the admin panel at `http://localhost:8000/admin`.
2. Create one project named `Store App`.
3. Copy the generated `Project API Key`.
4. Open `Project User Schema` if you want profile fields such as:
   - `first_name`
   - `last_name`
   - `phone`
5. Mark those fields with `Show In API` if you want them returned in responses.
6. For the simplest first integration, use these auth settings:
   - `email_verification_enabled = false`
   - `ghost_accounts_enabled = false`

Important:

- do not send `first_name`, `last_name`, or `phone` as top-level request fields
- send them inside `custom_fields`
- the current public API does not require `api_secret`

## Step 2: Configure Store App

In the store app, add:

```env
AUTH_CORE_BASE_URL=http://localhost:8000/api/v1/auth
AUTH_CORE_PROJECT_KEY=your-store-app-project-key
```

If the store app is also Laravel, add this to `config/services.php`:

```php
'auth_core' => [
    'base_url' => env('AUTH_CORE_BASE_URL'),
    'project_key' => env('AUTH_CORE_PROJECT_KEY'),
],
```

## Step 3: Create A Local Customer Reference

Inside the store app, keep a local table that points to the auth-core user.

Example migration:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('auth_user_id')->unique();
            $table->string('email');
            $table->string('display_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

The important column is `auth_user_id`.

## Step 4: Register A Store Customer

Example request:

```bash
curl --request POST "http://localhost:8000/api/v1/auth/register" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: your-store-app-project-key" \
  --data '{
    "email": "customer@example.com",
    "password": "password",
    "password_confirmation": "password",
    "custom_fields": {
      "first_name": "Ali",
      "last_name": "Saleh",
      "phone": "+218910000000"
    }
  }'
```

Typical success response when email verification is disabled:

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "plain-text-access-token",
    "refresh_token": "plain-text-refresh-token",
    "user": {
      "id": "auth-user-uuid",
      "project_id": "project-uuid",
      "email": "customer@example.com",
      "custom_fields": {
        "first_name": "Ali",
        "last_name": "Saleh",
        "phone": "+218910000000"
      }
    }
  }
}
```

After this response, the store app should save:

- `auth_user_id = data.user.id`
- `email = data.user.email`
- `display_name = custom_fields.first_name + custom_fields.last_name`

Example:

```php
Customer::query()->updateOrCreate(
    ['auth_user_id' => $payload['user']['id']],
    [
        'email' => $payload['user']['email'],
        'display_name' => trim(
            ($payload['user']['custom_fields']['first_name'] ?? '').' '.($payload['user']['custom_fields']['last_name'] ?? '')
        ),
    ],
);
```

## Step 5: Login From Store App

Example request:

```bash
curl --request POST "http://localhost:8000/api/v1/auth/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: your-store-app-project-key" \
  --data '{
    "email": "customer@example.com",
    "password": "password"
  }'
```

This returns:

- `access_token`
- `refresh_token`
- `user`

The store app can:

- store the tokens in a session
- store them in secure HTTP-only cookies
- or keep refresh tokens server-side and issue its own session to the frontend

For most backend-driven store apps, the safest pattern is:

- frontend sends email and password to the store backend
- store backend calls auth-core
- store backend stores the refresh token server-side or in an HTTP-only cookie

## Step 6: Read The Current User

Example request:

```bash
curl --request GET "http://localhost:8000/api/v1/auth/me" \
  --header "Accept: application/json" \
  --header "X-Project-Key: your-store-app-project-key" \
  --header "Authorization: Bearer your-access-token"
```

Use this when the store app needs to verify the signed-in customer or refresh profile data from auth-core.

## Step 7: Refresh The Access Token

When the access token expires, send the refresh token:

```bash
curl --request POST "http://localhost:8000/api/v1/auth/refresh" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: your-store-app-project-key" \
  --data '{
    "refresh_token": "your-refresh-token"
  }'
```

This returns a new:

- `access_token`
- `refresh_token`

Always replace the old refresh token with the new one after a successful refresh.

## Step 8: Logout

Example request:

```bash
curl --request POST "http://localhost:8000/api/v1/auth/logout" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "X-Project-Key: your-store-app-project-key" \
  --header "Authorization: Bearer your-access-token"
```

Logout revokes:

- the current access token
- all active refresh tokens for that store customer inside this auth project

## Laravel Example In Store App

If your store app is Laravel, this service is enough for the first integration:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AuthCoreClient
{
    private function request()
    {
        return Http::baseUrl(config('services.auth_core.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->withHeaders([
                'X-Project-Key' => config('services.auth_core.project_key'),
            ]);
    }

    public function register(array $payload): array
    {
        return $this->request()
            ->post('/register', $payload)
            ->throw()
            ->json('data');
    }

    public function login(string $email, string $password): array
    {
        return $this->request()
            ->post('/login', [
                'email' => $email,
                'password' => $password,
            ])
            ->throw()
            ->json('data');
    }

    public function me(string $accessToken): array
    {
        return $this->request()
            ->withToken($accessToken)
            ->get('/me')
            ->throw()
            ->json('data');
    }

    public function refresh(string $refreshToken): array
    {
        return $this->request()
            ->post('/refresh', [
                'refresh_token' => $refreshToken,
            ])
            ->throw()
            ->json('data');
    }

    public function logout(string $accessToken): void
    {
        $this->request()
            ->withToken($accessToken)
            ->post('/logout')
            ->throw();
    }
}
```

Example controller usage after login:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuthCoreClient;
use Illuminate\Http\Request;

class StoreLoginController extends Controller
{
    public function store(Request $request, AuthCoreClient $authCore): array
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $payload = $authCore->login($data['email'], $data['password']);
        $user = $payload['user'];
        $customFields = $user['custom_fields'] ?? [];

        $customer = Customer::query()->updateOrCreate(
            ['auth_user_id' => $user['id']],
            [
                'email' => $user['email'],
                'display_name' => trim(($customFields['first_name'] ?? '').' '.($customFields['last_name'] ?? '')),
            ],
        );

        return [
            'customer_id' => $customer->id,
            'auth_user_id' => $customer->auth_user_id,
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'],
        ];
    }
}
```

## Typical Store App Flow

The simplest full flow is:

1. Create one project in auth-core called `Store App`.
2. Copy its `Project API Key`.
3. Define any needed profile fields in `Project User Schema`.
4. Store app sends register or login requests with the same `X-Project-Key`.
5. Store app saves `auth_user_id` locally.
6. Store app uses `/me` to confirm the current user.
7. Store app uses `/refresh` when the access token expires.
8. Store app uses `/logout` when the customer signs out.

## Common Mistakes

- Sending `first_name`, `last_name`, or `phone` as top-level fields instead of `custom_fields`
- Creating multiple auth projects when the store app only needs one
- Forgetting to save `auth_user_id` locally
- Reusing the old refresh token after a successful refresh
- Mixing tokens from one project key with another project key
- Expecting `api_secret` to be required in the current public flow

## Recommended First Version

For the first store integration, keep it simple:

- one auth project only
- email verification disabled
- ghost accounts disabled
- custom fields only for the few profile values you actually need
- store app keeps `auth_user_id`, `email`, and `display_name` locally

After that is stable, you can add:

- email verification
- password reset UI
- OTP-based flows
- ghost account invitations
