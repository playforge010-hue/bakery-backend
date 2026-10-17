# bakery-backend

Laravel 12 API for Dwarkesh Bakers. The Next.js website is maintained in the separate `bakery-frontend` repository.

## Local setup

1. Run `composer install`.
2. Copy `.env.local.example` to `.env`, configure MySQL, then run `php artisan key:generate`.
3. Run `php artisan migrate`. The `/media` route serves approved public uploads; `php artisan storage:link` is optional for older `/storage` URLs.
4. Run `php artisan serve --host=0.0.0.0 --port=8000`.

This repository serves only the Laravel API; the Next.js site has its own repository and build. No Node.js/Vite build is needed here.

The storefront catalog can be loaded locally with `php artisan db:seed` and on Railway with `php artisan db:seed --force`. It adds the same categories, products, variants, options, hours, and banner as the local database; it does not create customers, orders, or admin credentials. Bundled catalog images are served from `public/demo-catalog` and remain available after redeployment. Review the sample menu, prices, contact details, and store map pin before accepting real orders. Create the first owner separately with `php artisan admin:create-owner`.

## Production configuration

For Railway, copy the variables in [`.env.example`](.env.example) into the backend service's Raw Editor and set a unique private `APP_KEY`. A local `.env.railway.copy` may be generated with the key already filled in; it is ignored by Git and must never be committed. `DB_URL` references `MYSQL_URL` from the `MySQL` service; this specific service defines `MYSQL_URL` as a private-network connection string and does not define `MYSQL_PRIVATE_URL`. Rename `MySQL` in the reference if your database service has a different Railway name. Laravel reads the MySQL host, port, database, user, and password from that single URL. The template uses the configured public API domain and allows the current local frontend origins, including its current mobile LAN address. Add the deployed frontend HTTPS origin to `CORS_ALLOWED_ORIGINS` when that domain exists, and update the LAN address if it changes. The public domain currently routes to Railway's detected port 8080.

The backend and MySQL services must be in the same Railway project and environment, and the database service must be running. Railway private hostnames do not resolve across projects. If the services were created in separate projects, add a MySQL service from the backend project's canvas, then reference that new service. If `/api/v1/health/database` reports connection error `2002` and Railway logs show blank `Host`, `Port`, and `Database`, the database references resolved to empty values. In the backend Variables tab, set `DB_URL` through Railway's reference-variable selector to the MySQL service's `MYSQL_URL`, or use the five separate `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` references. Do not mix both methods. Review and deploy the staged variable changes. Add each deployed frontend HTTPS origin to `CORS_ALLOWED_ORIGINS`, then redeploy the backend so browsers can call the API.

Railway's Railpack build reads [`railpack.json`](railpack.json) and installs `libatomic1` alongside its default Apt packages. No extra service variable is needed for that package.
Railway supplies the `PORT` variable for the PHP server. Keep the public domain's target port aligned with the port Railway detects (currently 8080).

The template sets `BROADCAST_CONNECTION=log` until a separate Reverb service and public WebSocket endpoint are configured. Set matching Reverb credentials and `BROADCAST_CONNECTION=reverb` on the backend and frontend when ready. `CUSTOMER_OTP_DRIVER=sms` intentionally fails closed: the current code has no production SMS provider and returns 503 for OTP requests. Customer login cannot work publicly until that provider is implemented. `MAIL_MAILER=log` similarly does not deliver email. Railway's filesystem is ephemeral unless a volume is attached, so mount persistent storage for uploaded images before public launch.

For uploaded profile, category, and product images, attach a Railway volume to the **backend** service at `/app/storage/app/public`. Keep `IMAGE_DISK=public` and set `IMAGE_BASE_URL` to `https://dwarkesh-backers.up.railway.app/media`. An empty new volume does not copy files from the previous container, so re-upload images that are missing after attaching it. Uploaded URLs are served by the `/media` route without requiring a `public/storage` symlink. See [Railway's volume guide](https://docs.railway.com/volumes) for the service mount path.

Set `LOG_CHANNEL=stack` and `LOG_STACK=stderr,daily` in the Railway backend service so unexpected application errors appear both in Railway deployment logs and in daily files under `/app/storage/logs`. The example retains 14 daily files. To keep these files across redeployments, attach a Railway volume mounted at `/app/storage/logs`; without a volume, the file is available only for the lifetime of the current container. Routine validation and authentication responses are not logged as application errors.

In the backend service's Railway **Settings → Deploy → Pre-deploy Command**, set `php artisan migrate --force --no-interaction` after the MySQL references work. New Railway services cannot opt into the older `railway.json` config-as-code mechanism, so this must be set in the service dashboard. A failed migration stops the new deployment and exposes the database error in the pre-deploy logs. Uploaded public images use `/media` without a storage symlink, but the image disk still needs persistent storage across redeployments. Start a supervised `php artisan queue:work --tries=3` process. For live support and order events, run `php artisan reverb:start` behind a TLS WebSocket proxy and set matching Reverb values in the frontend environment. Create the initial owner interactively with `php artisan admin:create-owner`.

**Production blocker:** customer OTP delivery currently supports only the local log driver. The application returns 503 for a non-log driver. Connect an SMS provider before enabling customer registration and login in production; do not use log-delivered OTPs on a public deployment.

The API health route is `/api/v1/health`. `/api/v1/health/database` checks the database connection and required catalog tables without returning credentials or hostnames. Project standards and development status are in `docs/`
