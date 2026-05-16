# Compete

A Laravel/Filament application for managing martial arts competitions.

## Requirements

- PHP 8.2+
- Composer
- Node.js / npm

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

## Deployment

After pulling new code, run the following to keep caches current:

```bash
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear
```

> **Note:** `config:cache` and `route:cache` merge all config/routes into single files for faster boot times. Run `php artisan config:clear` / `php artisan route:clear` if you need to debug config values locally (cached config ignores `.env` changes until re-cached).

## Testing

```bash
php artisan test
```
