# Store App + Laravel Integration Guide

This guide shows one simple setup only:

- `auth-core` is this Laravel project
- `Store App` is another Laravel app
- both are for one product only
- one `X-Project-Key` is used for the whole store

## Recommended Approach

For a Laravel store app, the easiest stable version is:

- the browser talks to the store app
- the store app talks to `auth-core` using Laravel's HTTP client
- the store app stores auth-core tokens in the Laravel session
- the store app keeps a local `customers` row with `auth_user_id`

## Architecture

```text
Browser
  -> Laravel Store App
      -> auth-core API

Store App DB
  customers.auth_user_id -> auth-core project_users.id
```

## URLs

In local development:

- auth-core admin panel: `http://localhost:8000/admin`
- auth-core API base URL: `http://localhost:8000/api/v1/auth`
- auth-core generated docs: `http://localhost:8000/docs/api`

## Step 1: Create The Store Project In Auth Core

Inside `auth-core`:

1. Log in to `http://localhost:8000/admin`
2. Create one project called `Store App`
3. Copy the generated `Project API Key`
4. In `Project User Schema`, add any profile fields you need, for example:
   - `first_name`
   - `last_name`
   - `phone`
5. Mark them `Show In API`

For the easiest first integration:

- `email_verification_enabled = false`
- `ghost_accounts_enabled = false`

## Step 2: Configure The Laravel Store App

Add environment variables:

```env
AUTH_CORE_BASE_URL=http://localhost:8000/api/v1/auth
AUTH_CORE_PROJECT_KEY=your-store-app-project-key
```

Add this to `config/services.php`:

```php
'auth_core' => [
    'base_url' => env('AUTH_CORE_BASE_URL'),
    'project_key' => env('AUTH_CORE_PROJECT_KEY'),
],
```

## Step 3: Create A Local Customer Reference

Create a local table in the store app:

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

The important field is:

```text
auth_user_id
```

That points to:

```text
auth-core -> project_users.id
```

## Step 4: Create The Auth Core Client

Create `app/Services/AuthCoreClient.php`:

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
            ->retry(2, 200)
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

## Step 5: Create The Customer Model

Create `app/Models/Customer.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'auth_user_id',
        'email',
        'display_name',
    ];
}
```

## Step 6: Create The Register Controller

Create `app/Http/Controllers/StoreRegisterController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuthCoreClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreRegisterController extends Controller
{
    public function store(Request $request, AuthCoreClient $authCore): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
            'first_name' => ['nullable', 'string'],
            'last_name' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
        ]);

        $payload = $authCore->register([
            'email' => $data['email'],
            'password' => $data['password'],
            'password_confirmation' => $data['password_confirmation'],
            'custom_fields' => array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn (?string $value): bool => filled($value)),
        ]);

        $user = $payload['user'];
        $customFields = $user['custom_fields'] ?? [];

        $customer = Customer::query()->updateOrCreate(
            ['auth_user_id' => $user['id']],
            [
                'email' => $user['email'],
                'display_name' => trim(($customFields['first_name'] ?? '').' '.($customFields['last_name'] ?? '')),
            ],
        );

        $request->session()->put('customer_id', $customer->id);
        $request->session()->put('auth_access_token', $payload['access_token']);
        $request->session()->put('auth_refresh_token', $payload['refresh_token']);
        $request->session()->regenerate();

        return redirect()->route('account.show');
    }
}
```

## Step 7: Create The Login Controller

Create `app/Http/Controllers/StoreLoginController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuthCoreClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreLoginController extends Controller
{
    public function store(Request $request, AuthCoreClient $authCore): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            $payload = $authCore->login($data['email'], $data['password']);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $payload['user'];
        $customFields = $user['custom_fields'] ?? [];

        $customer = Customer::query()->updateOrCreate(
            ['auth_user_id' => $user['id']],
            [
                'email' => $user['email'],
                'display_name' => trim(($customFields['first_name'] ?? '').' '.($customFields['last_name'] ?? '')),
            ],
        );

        $request->session()->put('customer_id', $customer->id);
        $request->session()->put('auth_access_token', $payload['access_token']);
        $request->session()->put('auth_refresh_token', $payload['refresh_token']);
        $request->session()->regenerate();

        return redirect()->route('account.show');
    }
}
```

## Step 8: Create The Account Controller

Create `app/Http/Controllers/StoreAccountController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\AuthCoreClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class StoreAccountController extends Controller
{
    public function show(Request $request, AuthCoreClient $authCore): View|RedirectResponse
    {
        $accessToken = $request->session()->get('auth_access_token');
        $refreshToken = $request->session()->get('auth_refresh_token');

        if (! is_string($accessToken)) {
            return redirect()->route('login.show');
        }

        try {
            $user = $authCore->me($accessToken);
        } catch (Throwable) {
            if (! is_string($refreshToken)) {
                return redirect()->route('login.show');
            }

            try {
                $refreshed = $authCore->refresh($refreshToken);

                $request->session()->put('auth_access_token', $refreshed['access_token']);
                $request->session()->put('auth_refresh_token', $refreshed['refresh_token']);

                $user = $refreshed['user'];
            } catch (Throwable) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login.show');
            }
        }

        return view('account.show', [
            'user' => $user,
        ]);
    }
}
```

## Step 9: Create The Logout Controller

Create `app/Http/Controllers/StoreLogoutController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\AuthCoreClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class StoreLogoutController extends Controller
{
    public function store(Request $request, AuthCoreClient $authCore): RedirectResponse
    {
        $accessToken = $request->session()->get('auth_access_token');

        if (is_string($accessToken)) {
            try {
                $authCore->logout($accessToken);
            } catch (Throwable) {
                // Ignore remote logout failures and clear local state anyway.
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }
}
```

## Step 10: Register Routes

In `routes/web.php`:

```php
use App\Http\Controllers\StoreAccountController;
use App\Http\Controllers\StoreLoginController;
use App\Http\Controllers\StoreLogoutController;
use App\Http\Controllers\StoreRegisterController;
use Illuminate\Support\Facades\Route;

Route::view('/login', 'auth.login')->name('login.show');
Route::post('/login', [StoreLoginController::class, 'store'])->name('login.store');

Route::view('/register', 'auth.register')->name('register.show');
Route::post('/register', [StoreRegisterController::class, 'store'])->name('register.store');

Route::get('/account', [StoreAccountController::class, 'show'])->name('account.show');
Route::post('/logout', [StoreLogoutController::class, 'store'])->name('logout.store');
```

## Step 11: Example Blade Login Form

Create `resources/views/auth/login.blade.php`:

```blade
<form method="POST" action="{{ route('login.store') }}">
    @csrf

    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Login</button>
</form>
```

## Step 12: Example Blade Register Form

Create `resources/views/auth/register.blade.php`:

```blade
<form method="POST" action="{{ route('register.store') }}">
    @csrf

    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="first_name" placeholder="First name">
    <input type="text" name="last_name" placeholder="Last name">
    <input type="text" name="phone" placeholder="Phone">
    <input type="password" name="password" placeholder="Password" required>
    <input type="password" name="password_confirmation" placeholder="Confirm password" required>

    <button type="submit">Create account</button>
</form>
```

## Step 13: Example Account Page

Create `resources/views/account/show.blade.php`:

```blade
<h1>My Account</h1>

<p>{{ $user['email'] }}</p>

<pre>{{ json_encode($user['custom_fields'], JSON_PRETTY_PRINT) }}</pre>

<form method="POST" action="{{ route('logout.store') }}">
    @csrf
    <button type="submit">Logout</button>
</form>
```

## Real Request Examples Against Auth Core

Register:

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

Login:

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

## Common Mistakes

- Forgetting to keep `auth_user_id` locally
- Sending `first_name`, `last_name`, or `phone` as top-level fields to auth-core
- Not regenerating the session after login
- Reusing the old refresh token after a successful refresh
- Letting the browser call auth-core directly when the store backend should own the session

## Recommended First Version

Start with:

- one auth project only
- one `customers` table with `auth_user_id`
- auth-core tokens stored in the Laravel session
- registration, login, me, refresh, and logout only
- email verification disabled until the base flow is stable
