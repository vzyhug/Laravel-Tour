# Render forwarding to the existing ngrok tunnel

This service forwards HTTP traffic. Laravel, MySQL and ngrok continue running on the local computer.
It does not deploy Laravel or copy the database to Render.

## Render New Web Service

Use the GitHub repository containing this directory and set:

| Field | Value |
| --- | --- |
| Language | Node |
| Branch | main, after these files have been pushed |
| Root Directory | deploy/render-ngrok |
| Build Command | npm run check |
| Start Command | npm start |
| Health Check Path | /up |
| Instance Type | Free, if available; choose a paid plan only intentionally |
| Environment: NGROK_UPSTREAM | https://compulsorily-churchiest-florencio.ngrok-free.dev |
| Environment: NODE_VERSION | 22 |

No Laravel APP_KEY, database password, Supabase secret or SePay secret belongs in this Render proxy.
Render supplies RENDER_EXTERNAL_URL. Only set PUBLIC_ORIGIN when explicitly overriding the public origin.

After Render reports the actual public service URL, set APP_URL in the LOCAL Laravel .env to that URL.
Keep APP_DEBUG=false, SESSION_DOMAIN=null and use the existing loopback trusted proxy configuration.
Run `php artisan config:clear` locally. Do not modify SePay or database credentials.

Forwarded host and protocol headers allow Laravel to generate links for the Render URL.
The proxy also rewrites redirects pointing at the ngrok origin, preserves cookies, request bodies,
CSRF headers, query strings and streamed assets, and adds ngrok-skip-browser-warning.
There are no application dependencies or npm installation/build scripts to run.

## Verification

Check the home page, /login, a tour, assets and a redirect on the actual Render address.
Then sign in manually to confirm session cookies, CSRF forms, favorites and payment status polling.

/up is forwarded to Laravel, so a stopped tunnel causes a failed health check.
Keep the computer awake and Laravel, MySQL and ngrok running. Free service sleep and ngrok usage
limits still apply. Proxy forwarding does not support WebSocket upgrades; current payment polling
uses ordinary HTTP. Request-IP throttling can group visitors behind the proxy.

This adds Render and ngrok to the path used by customer traffic. For independent hosting,
deploy Laravel and a remotely accessible database instead of using this proxy.

Official references:
- https://render.com/docs/web-services
- https://render.com/docs/node-version
- https://ngrok.com/docs/pricing-limits/free-plan-limits
