# MarketLens Platform

The MarketLens Platform is the central cloud application for business
administration, subscriptions, device management, synchronization ingress,
Monitoring App authentication, and owner-facing reporting.

It does not operate business sales directly. Business operational writes originate in
the Business Server and are synchronized to the Platform for cloud reporting and remote
visibility.

## Stack

- Laravel 13
- PHP 8.3+
- Livewire
- Alpine.js
- Tailwind CSS 4
- Flux UI
- PostgreSQL
- Redis
- Horizon
- Sanctum
- Reverb
- Pest
- Pint
- Larastan
- Vite

## Setup

```bash
cd apps/platform
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
```

This app was generated on Windows. Horizon requires `pcntl` and `posix`, so run
Horizon under Linux, WSL, or container PHP.

Start local PostgreSQL, Redis, and Mailpit with:

```bash
docker compose up -d
```

## PostgreSQL

Set PostgreSQL values in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketlens_platform
DB_USERNAME=marketlens
DB_PASSWORD=marketlens
```

Run migrations:

```bash
php artisan migrate
```

## Redis

Redis backs cache, queues, Horizon, and Reverb-related workflows:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Queue Workers

Use Redis queues for synchronization and reporting work:

```bash
php artisan queue:work redis --queue=sync,reports,default
```

Synchronization processing should dispatch queued jobs and keep request handlers
thin.

## Horizon

Publish and tune Horizon configuration when operational infrastructure is ready:

```bash
php artisan horizon:install
php artisan horizon
```

Run Horizon in Linux/container PHP where `pcntl` and `posix` are available.

## Reverb

Reverb supports real-time Platform UI updates:

```bash
php artisan reverb:install
php artisan reverb:start
```

Keep Reverb app IDs, keys, and secrets outside Git.

## Scheduler

The scheduler should own recurring summary generation, subscription checks,
sync-health checks, cache refreshes, and cleanup jobs:

```bash
php artisan schedule:run
```

Production should run the scheduler every minute through cron or the deployment
platform.

## Tests

```bash
php artisan test
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Pest core is installed. The Pest Laravel plugin is not yet PHP 8.3-compatible
with Laravel 13, so Laravel feature tests currently run through the framework
test harness.

## API Documentation

Versioned APIs begin under `/api/v1`:

- `/api/v1/auth/*`
- `/api/v1/device/*`
- `/api/v1/sync/*`
- `/api/v1/monitoring/*`

Document endpoint contracts in `docs/api` before implementing full behavior.
The OpenAPI foundation lives at `docs/openapi.yaml`.

## Deployment Overview

Production deployment should provide:

- PHP 8.3+ runtime with required extensions.
- PostgreSQL.
- Redis.
- Queue workers and Horizon.
- Reverb process where real-time updates are enabled.
- Scheduler.
- Secret-managed environment configuration.
- S3-compatible object storage where configured.

Never commit production secrets, generated credentials, or customer data.
