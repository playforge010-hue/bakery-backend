# bakery-backend

Laravel 12 API for Dwarkesh Bakers. The Next.js website is maintained in the separate `bakery-frontend` repository.

## Local setup

1. Run `composer install`.
2. Copy `.env.example` to `.env`, configure MySQL, then run `php artisan key:generate`.
3. Run `php artisan migrate` and `php artisan storage:link`.
4. Run `php artisan serve --host=0.0.0.0 --port=8000`.

The local demo catalog can be loaded with `php artisan db:seed`. It is disabled when `APP_ENV=production`.

## Production configuration

Use [`.env.production.example`](.env.production.example) as a checklist for the deployment host. Create the real `.env` on that host, supply unique secrets and MySQL credentials, and never commit it. Point the web server at `public/`, serve the API over HTTPS, and keep `storage/` persistent and writable.

After installing dependencies, run `php artisan key:generate`, `php artisan migrate --force`, `php artisan storage:link`, and `php artisan optimize`. Start a supervised `php artisan queue:work --tries=3` process. For live support and order events, run `php artisan reverb:start` behind a TLS WebSocket proxy and set matching Reverb values in the frontend environment. Create the initial owner interactively with `php artisan admin:create-owner`.

**Production blocker:** customer OTP delivery currently supports only the local log driver. The application returns 503 for a non-log driver. Connect an SMS provider before enabling customer registration and login in production; do not use log-delivered OTPs on a public deployment.

The API health route is `/api/v1/health`. Project standards and development status are in `docs/`.
