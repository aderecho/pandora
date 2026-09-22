# FileServer Admin Dashboard

Vue 3 + TypeScript + Tailwind CSS SPA for managing the FileServer platform:
buckets, objects (file browser), API tokens, users, and audit logs.

## Develop

```bash
npm install
cp .env.example .env   # point VITE_API_BASE_URL/VITE_API_ROOT_URL at your API
npm run dev
```

Requires the `api/` Laravel backend running and reachable at the configured
URL, with `FILESERVER_CORS_ALLOWED_ORIGINS` / `SANCTUM_STATEFUL_DOMAINS`
including this app's origin (see `api/.env.example`).

## Build

```bash
npm run build   # type-checks then builds to dist/
```

## Structure

- `src/api/` — typed Axios wrappers per resource (buckets, objects, tokens, users, auditLogs, dashboard)
- `src/stores/` — Pinia stores (`auth`, `toast`)
- `src/router/` — routes + auth/role navigation guards
- `src/layouts/AppLayout.vue` — sidebar + topbar shell
- `src/views/` — one view per route
- `src/components/` — `ui/` primitives (Modal, ConfirmDialog, Badge, ToastStack, StatTile) plus feature components (`buckets/`, `files/`, `tokens/`, `users/`)
- `src/types/api.ts` — TypeScript interfaces mirroring the backend's API resources

## Auth model

The dashboard authenticates via a Laravel Sanctum stateful SPA session
(cookie + CSRF token), not a bearer token — `src/api/http.ts` fetches
`/sanctum/csrf-cookie` before login and axios attaches `X-XSRF-TOKEN`
automatically on every subsequent request. Navigation is gated client-side
by role (`src/stores/auth.ts`) purely for UX; the API re-authorizes every
request server-side regardless of what the UI shows.
