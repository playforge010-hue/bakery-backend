# bakery-backend

Laravel 12 API for Dwarkesh Bakers. The Next.js website is maintained in the separate `bakery-frontend` repository.

## Local setup

1. Run `composer install`.
2. Copy `.env.local.example` to `.env`, configure MySQL, then run `php artisan key:generate`.
3. Run `php artisan migrate` and `php artisan storage:link`.
4. Run `php artisan serve --host=0.0.0.0 --port=8000`.

This repository serves only the Laravel API; the Next.js site has its own repository and build. No Node.js/Vite build is needed here.

The local demo catalog can be loaded with `php artisan db:seed`. It is disabled when `APP_ENV=production`.

## Production configuration

For Railway, copy the variables in [`.env.example`](.env.example) into the backend service's Raw Editor and set a unique private `APP_KEY`. A local `.env.railway.copy` may be generated with the key already filled in; it is ignored by Git and must never be committed. The `MySQL` service references resolve database credentials at runtime; rename `MySQL` in the references if your database service has a different Railway name. The template uses the configured public API domain and allows the current local frontend origins, including its current mobile LAN address. Add the deployed frontend HTTPS origin to `CORS_ALLOWED_ORIGINS` when that domain exists, and update the LAN address if it changes. The public domain currently routes to Railway's detected port 8080.

Railway's Railpack build reads [`railpack.json`](railpack.json) and installs `libatomic1` alongside its default Apt packages. No extra service variable is needed for that package.
Railway supplies the `PORT` variable for the PHP server. Keep the public domain's target port aligned with the port Railway detects (currently 8080).

The template sets `BROADCAST_CONNECTION=log` until a separate Reverb service and public WebSocket endpoint are configured. Set matching Reverb credentials and `BROADCAST_CONNECTION=reverb` on the backend and frontend when ready. `CUSTOMER_OTP_DRIVER=sms` intentionally fails closed: the current code has no production SMS provider and returns 503 for OTP requests. Customer login cannot work publicly until that provider is implemented. `MAIL_MAILER=log` similarly does not deliver email. Railway's filesystem is ephemeral unless a volume is attached, so mount persistent storage for uploaded images before public launch.

Railway runs `php artisan migrate --force --no-interaction` before each deployment using [`railway.json`](railway.json). A failed migration stops the new deployment and exposes the database error in the pre-deploy logs. Run `php artisan storage:link` in the running service, and start a supervised `php artisan queue:work --tries=3` process. For live support and order events, run `php artisan reverb:start` behind a TLS WebSocket proxy and set matching Reverb values in the frontend environment. Create the initial owner interactively with `php artisan admin:create-owner`.

**Production blocker:** customer OTP delivery currently supports only the local log driver. The application returns 503 for a non-log driver. Connect an SMS provider before enabling customer registration and login in production; do not use log-delivered OTPs on a public deployment.

The API health route is `/api/v1/health`. `/api/v1/health/database` checks the database connection and required catalog tables without returning credentials or hostnames. Project standards and development status are in `docs/`.
