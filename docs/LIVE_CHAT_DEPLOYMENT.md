# Live support chat on Railway and Vercel

Support messages are saved through authenticated HTTP endpoints. Live delivery and
typing indicators require Laravel Reverb; `BROADCAST_CONNECTION=log` only writes
events to a log and cannot update another browser. Without Reverb, the web inbox
polls for new messages while it remains open.

1. Create a second Railway service from the backend repository. Set its start
   command to `php artisan reverb:start --host=0.0.0.0 --port=8080`, then give it a
   public HTTPS domain targeting port `8080`. Keep the Laravel HTTP service on
   its existing port. Use one Reverb replica unless Redis-based scaling is set up.
2. Give the Reverb service and Laravel HTTP service the same
   `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`. Keep the secret out
   of Vercel. Set `REVERB_ALLOWED_ORIGINS` on Reverb to the exact frontend HTTPS
   origin(s), separated by commas. Set `REVERB_HOST` to the new Reverb domain
   without `https://`, `REVERB_PORT=443`, and `REVERB_SCHEME=https` on both
   services. Set `BROADCAST_CONNECTION=reverb` on the Laravel HTTP service.
3. In Vercel, set `NEXT_PUBLIC_REVERB_APP_KEY` to the matching public app key,
   `NEXT_PUBLIC_REVERB_HOST` to the Reverb domain without a scheme,
   `NEXT_PUBLIC_REVERB_PORT=443`, and `NEXT_PUBLIC_REVERB_SCHEME=https`.
   Redeploy the frontend after changing these build-time variables. Ensure the
   Laravel service's `CORS_ALLOWED_ORIGINS` includes the exact frontend origin
   so `/api/broadcasting/auth` accepts authenticated browser requests.
4. Mount persistent storage for both public uploads and private support images.
   The default private support disk is `storage/app/private`, while public
   catalog/profile uploads use `storage/app/public`. A volume mounted only at
   `/app/storage/app/public` does not preserve chat images. Plan the volume
   migration before replacing an existing mount; a new empty volume does not
   contain earlier uploads.

To diagnose a failed chat, check the browser Network tab. A failed
`/api/v1/support/reply-ticket` or `/api/v1/admin/support/reply-ticket` request
is an HTTP/auth/validation/storage problem. If those requests succeed but the
other browser does not update, inspect the WebSocket connection and
`POST /api/broadcasting/auth`. A `401` means the bearer token is missing or
expired; a `403` means the account or token cannot join that private channel.
An attachment request returning `404` can mean its file was lost from the
temporary container filesystem even though the message remains in MySQL.
