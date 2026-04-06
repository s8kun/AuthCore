<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# Auth-as-a-Service

A multi-tenant authentication platform built with Laravel, Filament, and Sanctum. Provides isolated authentication systems for multiple client projects through a single API.

## Features

- **Multi-Tenant Authentication**: Each project gets its own isolated user base
- **Comprehensive Auth Flows**: Registration, login, logout, token refresh, password reset
- **OTP Verification**: Email verification, password reset, login verification
- **Ghost Accounts**: Pre-register accounts for later claiming
- **Audit Logging**: Full API request and authentication event logging
- **Project Management**: Filament admin panel for project configuration
- **Custom Email Templates**: Per-project email templates with variables
- **Custom SMTP Support**: Project-specific email settings
- **Rate Limiting**: Project-scoped rate limiting
- **API-First Design**: RESTful API with proper HTTP status codes

## Quick Start

### Installation

1. Clone the repository:

```bash
git clone https://github.com/yourusername/auth-as-a-service.git
cd auth-as-a-service
```

2. Install dependencies:

```bash
composer install
npm install
```

3. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

4. Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auth_service
DB_USERNAME=root
DB_PASSWORD=
```

5. Run migrations and seeders:

```bash
php artisan migrate
php artisan db:seed
```

6. Generate project encryption keys:

```bash
php artisan passport:keys --ansi
```

7. Build frontend assets:

```bash
npm run build
```

### Running the Application

#### Development Mode

```bash
composer run dev
```

This starts:

- PHP development server (`localhost:8000`)
- Queue worker
- Log monitor
- Vite dev server

#### Production Mode

```bash
php artisan serve
```

#### Testing

```bash
php artisan test
```

## Usage Guide

### 1. Platform Setup

1. **Create Admin Account**:
    - Visit `/admin/login`
    - Default admin: `admin@example.com` / `password`

2. **Create a Project**:
    - Navigate to `/admin/projects`
    - Click "New Project"
    - Fill in project details
    - Save to generate API keys

3. **Configure Project Settings**:
    - **Auth Settings**: Configure authentication modes, rate limits
    - **Mail Settings**: Set up email (platform or custom SMTP)
    - **Email Templates**: Customize email content
    - **Integration**: Get API keys and integration details

### 2. API Integration

#### Authentication Headers

All API requests require:

```http
X-Project-Key: your_project_api_key
Content-Type: application/json
Accept: application/json
```

#### Authentication Flow

**1. Register a User**

```http
POST /api/v1/auth/register
```

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123"
}
```

**2. Login**

```http
POST /api/v1/auth/login
```

```json
{
    "email": "john@example.com",
    "password": "secret123"
}
```

Response includes `access_token` and `refresh_token`.

**3. Authenticated Requests**

```http
GET /api/v1/auth/me
Authorization: Bearer {access_token}
```

**4. Refresh Token**

```http
POST /api/v1/auth/refresh
```

```json
{
    "refresh_token": "{refresh_token}"
}
```

**5. Logout**

```http
POST /api/v1/auth/logout
Authorization: Bearer {access_token}
```

**6. Password Reset**

```http
POST /api/v1/auth/forgot-password
```

```json
{
    "email": "john@example.com"
}
```

**7. OTP Verification**

```http
POST /api/v1/auth/send-otp
```

```json
{
    "email": "john@example.com",
    "purpose": "email_verification"
}
```

**8. Ghost Accounts**

```http
POST /api/v1/auth/ghost-accounts
```

```json
{
    "emails": ["user1@example.com", "user2@example.com"]
}
```

### 3. Rate Limiting

- **Default**: 100 requests/minute per project
- **Authentication endpoints**: 10 requests/minute per IP
- Configurable per project in Filament admin

### 4. Error Handling

All API endpoints return standardized error responses:

```json
{
    "message": "Validation error",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

HTTP Status Codes:

- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `429`: Too Many Requests
- `500`: Server Error

## Project Structure

```
app/
├── Enums/              # Application enums (AuthEventType, ProjectAuthMode, etc.)
├── Filament/           # Admin panel resources and pages
├── Http/
│   ├── Controllers/    # API controllers
│   ├── Middleware/     # Custom middleware
│   ├── Requests/       # Form request validation
│   └── Resources/      # API resources
├── Jobs/               # Queued jobs
├── Mail/               # Mailables
├── Models/             # Eloquent models
├── Providers/          # Service providers
├── Services/           # Business logic services
└── Support/            # Helper classes
```

## Database Schema

### Core Tables:

- `users` - Platform administrators
- `projects` - Client projects with API keys
- `project_users` - End users per project
- `project_auth_settings` - Per-project auth configuration
- `project_mail_settings` - Email settings
- `project_email_templates` - Custom email templates
- `api_request_logs` - API audit trail
- `auth_event_logs` - Authentication events

## Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=ProjectAuthApiTest

# Run with coverage
php artisan test --coverage
```

## API Documentation

Complete API documentation is available at `/docs/api` when running the application.

For detailed API reference, see [API.md](./API.md).

For detailed Project overview,see [Project Overview](./PROJECT_OVERVIEW.md).

## Deployment

### Production Requirements

- PHP 8.3+
- MySQL 8.0+ or PostgreSQL 13+
- Redis (recommended for queues/cache)
- Supervisor (for queue workers)

### Deployment Steps

1. Set up web server (Nginx/Apache)
2. Configure `.env` production values
3. Run `composer install --no-dev`
4. Run `npm run build`
5. Run `php artisan config:cache`
6. Run `php artisan route:cache`
7. Run `php artisan view:cache`
8. Set up Supervisor for queue workers
9. Configure SSL/TLS

## Support

- **Documentation**: `/docs/api` for API docs
- **Admin Panel**: `/admin` for project management
- **Issues**: GitHub Issues for bug reports
- **Security**: Report vulnerabilities to elferjani7@gmail.com

## Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md) before submitting pull requests.

## License

Auth-as-a-Service is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
