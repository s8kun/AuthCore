# Store App + Next.js Integration Guide

This guide shows one simple setup only:

- `auth-core` is this Laravel project
- `Store App` is a Next.js app
- both are for one product only
- one `X-Project-Key` is used for the whole store

This guide assumes a modern Next.js App Router project.

## Recommended Approach

For a Next.js store app, the safest first version is:

- the browser talks only to the Next.js app
- the Next.js app talks to `auth-core`
- access and refresh tokens are stored in secure HTTP-only cookies
- the store app keeps a local `customers` record with `auth_user_id`

Avoid storing auth-core tokens in `localStorage`.

## Architecture

```text
Browser
  -> Next.js Store App
      -> auth-core API

Store App DB
  customers.auth_user_id -> auth-core project_users.id
```

## URLs

In local development:

- auth-core admin panel: `http://localhost:8000/admin`
- auth-core API base URL: `http://localhost:8000/api/v1/auth`
- auth-core generated docs: `http://localhost:8000/docs/api`
- Next.js store app example URL: `http://localhost:3000`

## Step 1: Create The Store Project In Auth Core

Inside `auth-core`:

1. Log in to `http://localhost:8000/admin`
2. Create one project called `Store App`
3. Copy the generated `Project API Key`
4. In `Project User Schema`, add fields you want to expose to the store app, for example:
   - `first_name`
   - `last_name`
   - `phone`
5. Make sure these fields are marked `Show In API`

For the easiest first integration:

- `email_verification_enabled = false`
- `ghost_accounts_enabled = false`

## Step 2: Configure The Next.js App

Add environment variables:

```env
AUTH_CORE_BASE_URL=http://localhost:8000/api/v1/auth
AUTH_CORE_PROJECT_KEY=your-store-app-project-key
```

## Step 3: Store A Local Customer Reference

Your Next.js app should keep a local customer record that points to the auth-core user.

If you use Prisma, a simple model looks like this:

```prisma
model Customer {
  id          Int     @id @default(autoincrement())
  authUserId  String  @unique
  email       String
  displayName String?
  createdAt   DateTime @default(now())
  updatedAt   DateTime @updatedAt
}
```

The important field is:

```text
authUserId
```

That value should match:

```text
auth-core -> project_users.id
```

## Suggested File Structure

```text
app/
  api/
    auth/
      login/route.ts
      register/route.ts
      me/route.ts
      logout/route.ts
  login/page.tsx
  register/page.tsx
  account/page.tsx
lib/
  auth-core.ts
  customer-repository.ts
```

## Step 4: Create An Auth Core Client

Create `lib/auth-core.ts`:

```ts
const AUTH_CORE_BASE_URL = process.env.AUTH_CORE_BASE_URL!;
const AUTH_CORE_PROJECT_KEY = process.env.AUTH_CORE_PROJECT_KEY!;

type AuthCoreUser = {
  id: string;
  project_id: string;
  email: string;
  custom_fields: Record<string, unknown>;
  email_verified_at: string | null;
  last_login_at: string | null;
  is_active: boolean;
  is_ghost: boolean;
  claimed_at: string | null;
  invited_at: string | null;
  ghost_source: string | null;
  must_set_password: boolean;
  must_verify_email: boolean;
  created_at: string | null;
  updated_at: string | null;
};

type TokenPayload = {
  token_type: "Bearer";
  access_token: string;
  refresh_token: string;
  expires_at: string | null;
  expires_in_seconds: number | null;
  refresh_token_expires_at: string | null;
  refresh_token_expires_in_seconds: number | null;
  user: AuthCoreUser;
};

async function authCoreFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  return fetch(`${AUTH_CORE_BASE_URL}${path}`, {
    ...init,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Project-Key": AUTH_CORE_PROJECT_KEY,
      ...(init.headers ?? {}),
    },
  });
}

export async function registerWithAuthCore(payload: {
  email: string;
  password: string;
  password_confirmation: string;
  custom_fields?: Record<string, unknown>;
}): Promise<TokenPayload> {
  const response = await authCoreFetch("/register", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    throw await response.json();
  }

  return (await response.json()).data as TokenPayload;
}

export async function loginWithAuthCore(payload: {
  email: string;
  password: string;
}): Promise<TokenPayload> {
  const response = await authCoreFetch("/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    throw await response.json();
  }

  return (await response.json()).data as TokenPayload;
}

export async function meFromAuthCore(accessToken: string): Promise<AuthCoreUser> {
  const response = await authCoreFetch("/me", {
    method: "GET",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

  if (!response.ok) {
    throw response;
  }

  return (await response.json()).data as AuthCoreUser;
}

export async function refreshWithAuthCore(refreshToken: string): Promise<TokenPayload> {
  const response = await authCoreFetch("/refresh", {
    method: "POST",
    body: JSON.stringify({
      refresh_token: refreshToken,
    }),
  });

  if (!response.ok) {
    throw await response.json();
  }

  return (await response.json()).data as TokenPayload;
}

export async function logoutFromAuthCore(accessToken: string): Promise<void> {
  const response = await authCoreFetch("/logout", {
    method: "POST",
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });

  if (!response.ok) {
    throw await response.json();
  }
}
```

## Step 5: Save The Local Customer

Create `lib/customer-repository.ts`.

Replace the body with your actual Prisma or database calls:

```ts
export async function upsertCustomerFromAuthUser(user: {
  id: string;
  email: string;
  custom_fields?: Record<string, unknown>;
}) {
  const customFields = user.custom_fields ?? {};
  const firstName = String(customFields.first_name ?? "");
  const lastName = String(customFields.last_name ?? "");

  const displayName = `${firstName} ${lastName}`.trim() || null;

  return {
    authUserId: user.id,
    email: user.email,
    displayName,
  };
}
```

In a real app, this function should `upsert` your local `customers` table.

## Step 6: Create The Register Route Handler

Create `app/api/auth/register/route.ts`:

```ts
import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { registerWithAuthCore } from "@/lib/auth-core";
import { upsertCustomerFromAuthUser } from "@/lib/customer-repository";

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const payload = await registerWithAuthCore(body);

    await upsertCustomerFromAuthUser(payload.user);

    const cookieStore = await cookies();

    cookieStore.set("auth_access_token", payload.access_token, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
    });

    cookieStore.set("auth_refresh_token", payload.refresh_token, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
    });

    return NextResponse.json({
      data: {
        user: payload.user,
      },
    });
  } catch (error) {
    return NextResponse.json(error, { status: 422 });
  }
}
```

## Step 7: Create The Login Route Handler

Create `app/api/auth/login/route.ts`:

```ts
import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { loginWithAuthCore } from "@/lib/auth-core";
import { upsertCustomerFromAuthUser } from "@/lib/customer-repository";

export async function POST(request: Request) {
  try {
    const body = await request.json();
    const payload = await loginWithAuthCore(body);

    await upsertCustomerFromAuthUser(payload.user);

    const cookieStore = await cookies();

    cookieStore.set("auth_access_token", payload.access_token, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
    });

    cookieStore.set("auth_refresh_token", payload.refresh_token, {
      httpOnly: true,
      sameSite: "lax",
      secure: process.env.NODE_ENV === "production",
      path: "/",
    });

    return NextResponse.json({
      data: {
        user: payload.user,
      },
    });
  } catch (error) {
    return NextResponse.json(error, { status: 422 });
  }
}
```

## Step 8: Create The Current User Route Handler

Create `app/api/auth/me/route.ts`:

```ts
import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { meFromAuthCore, refreshWithAuthCore } from "@/lib/auth-core";

export async function GET() {
  const cookieStore = await cookies();
  const accessToken = cookieStore.get("auth_access_token")?.value;
  const refreshToken = cookieStore.get("auth_refresh_token")?.value;

  if (!accessToken) {
    return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
  }

  try {
    const user = await meFromAuthCore(accessToken);

    return NextResponse.json({ data: user });
  } catch (error) {
    if (!refreshToken) {
      return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
    }

    try {
      const refreshed = await refreshWithAuthCore(refreshToken);

      cookieStore.set("auth_access_token", refreshed.access_token, {
        httpOnly: true,
        sameSite: "lax",
        secure: process.env.NODE_ENV === "production",
        path: "/",
      });

      cookieStore.set("auth_refresh_token", refreshed.refresh_token, {
        httpOnly: true,
        sameSite: "lax",
        secure: process.env.NODE_ENV === "production",
        path: "/",
      });

      return NextResponse.json({ data: refreshed.user });
    } catch {
      cookieStore.delete("auth_access_token");
      cookieStore.delete("auth_refresh_token");

      return NextResponse.json({ message: "Unauthenticated." }, { status: 401 });
    }
  }
}
```

## Step 9: Create The Logout Route Handler

Create `app/api/auth/logout/route.ts`:

```ts
import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { logoutFromAuthCore } from "@/lib/auth-core";

export async function POST() {
  const cookieStore = await cookies();
  const accessToken = cookieStore.get("auth_access_token")?.value;

  if (accessToken) {
    try {
      await logoutFromAuthCore(accessToken);
    } catch {
      // Ignore logout errors and clear local cookies anyway.
    }
  }

  cookieStore.delete("auth_access_token");
  cookieStore.delete("auth_refresh_token");

  return NextResponse.json({
    data: {
      message: "Logged out successfully.",
    },
  });
}
```

## Step 10: Login Page Example

Create `app/login/page.tsx`:

```tsx
"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const response = await fetch("/api/auth/login", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        email,
        password,
      }),
    });

    if (!response.ok) {
      const payload = await response.json();
      setError(payload?.errors?.email?.[0] ?? "Login failed.");
      return;
    }

    router.push("/account");
    router.refresh();
  }

  return (
    <form onSubmit={handleSubmit}>
      <input
        type="email"
        value={email}
        onChange={(event) => setEmail(event.target.value)}
        placeholder="Email"
      />
      <input
        type="password"
        value={password}
        onChange={(event) => setPassword(event.target.value)}
        placeholder="Password"
      />
      <button type="submit">Login</button>
      {error ? <p>{error}</p> : null}
    </form>
  );
}
```

## Step 11: Protected Account Page Example

Create `app/account/page.tsx`:

```tsx
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { meFromAuthCore } from "@/lib/auth-core";

export default async function AccountPage() {
  const cookieStore = await cookies();
  const accessToken = cookieStore.get("auth_access_token")?.value;

  if (!accessToken) {
    redirect("/login");
  }

  try {
    const user = await meFromAuthCore(accessToken);

    return (
      <main>
        <h1>My Account</h1>
        <p>{user.email}</p>
        <pre>{JSON.stringify(user.custom_fields, null, 2)}</pre>
      </main>
    );
  } catch {
    redirect("/login");
  }
}
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

- Storing auth-core tokens in `localStorage`
- Calling auth-core directly from the browser when you want the Next.js app to manage cookies
- Forgetting to save `auth_user_id` locally
- Sending `first_name`, `last_name`, or `phone` as top-level fields instead of inside `custom_fields`
- Reusing the old refresh token after a successful refresh

## Recommended First Version

Start with:

- one auth project only
- route handlers for login, register, me, and logout
- secure HTTP-only cookies
- local `customers` table keyed by `auth_user_id`
- email verification disabled until the basic flow is stable
