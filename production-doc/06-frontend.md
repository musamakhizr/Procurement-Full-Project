# 06 · React / Vite Frontend

## Stack

| | |
|---|---|
| Framework | React 18.3 + react-router 7 |
| Build  | Vite 6.3 |
| Styling | Tailwind CSS 4 + Radix UI primitives + MUI |
| HTTP   | axios (with bearer token interceptor) |
| Source dir | `public/frontend/app/` |
| Build output | `public/frontend/dist/` |
| Public URL base | `/frontend/dist/` |

## Source layout

```
public/frontend/
├── app/
│   ├── App.tsx
│   ├── main.tsx
│   ├── routes.tsx          (react-router config)
│   ├── api.ts              (axios + endpoint helpers)
│   ├── components/
│   ├── pages/              (HomePage, MarketplacePage, ProductDetailPage, …)
│   ├── layouts/
│   └── contexts/           (AuthContext, ProcurementListContext, …)
├── styles/
│   ├── index.css
│   ├── tailwind.css
│   └── theme.css
├── package.json
├── package-lock.json       (committed for reproducible builds)
├── vite.config.ts
└── .env                    (VITE_API_BASE_URL=https://hexugo.com/api)
```

## Vite config (key settings)

```ts
export default defineConfig({
  base: '/frontend/dist/',          // ← critical: emitted index.html references this prefix
  plugins: [react(), tailwindcss()],
  resolve: { alias: { '@': './app' } },
  assetsInclude: ['**/*.svg', '**/*.csv'],
});
```

> If you ever change `base`, you must also update the nginx location blocks in `deploy/templates/nginx-hexugo.conf` and the `try_files` fallback that serves the SPA HTML.

## Building

```bash
cd public/frontend
npm ci             # install exact versions from package-lock.json
npm run build      # outputs public/frontend/dist/
```

The deploy script (`deploy/scripts/deploy.sh`) does this automatically on every release.

## How the SPA is served

Nginx (server block in `deploy/templates/nginx-hexugo.conf`):

```nginx
# Static assets (1y cache, immutable)
location /frontend/dist/assets/ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    try_files $uri =404;
}

# /frontend/dist/ — SPA HTML fallback for any other path under it
location /frontend/dist/ {
    try_files $uri /frontend/dist/index.html;
}

# Root path → React index.html (NOT Laravel welcome)
location = / {
    try_files /frontend/dist/index.html =404;
}

# Anything else not matched as file → React (SPA client-side routing)
location / {
    try_files $uri /frontend/dist/index.html;
}
```

This means:
- `https://hexugo.com/` → React mount
- `https://hexugo.com/marketplace` → React renders Marketplace page
- `https://hexugo.com/product/42` → React renders ProductDetail
- `https://hexugo.com/api/products` → Laravel JSON
- `https://hexugo.com/storage/uploads/foo.png` → static file
- `https://hexugo.com/frontend/dist/assets/index-XXX.js` → JS bundle (1y cache)

## Auth flow

1. User submits login form → `POST https://hexugo.com/api/auth/login`
2. Laravel returns `{ token, user }`
3. axios interceptor stores `token` in `localStorage['auth_token']`
4. Subsequent requests carry `Authorization: Bearer <token>`
5. `AuthContext` provides `user` to React tree
6. `ProtectedRoute` redirects to `/sign-in` if no user

## Local development

```bash
cd public/frontend
echo "VITE_API_BASE_URL=http://127.0.0.1:8000/api" > .env
npm install
npm run dev      # vite at http://localhost:5173
```

Backend dev (separate terminal, project root):
```bash
php artisan serve            # http://127.0.0.1:8000
php artisan queue:listen     # local worker
```

## Common asset-path issue (was the original "blank page" bug)

If you see a white screen with `404` for `/assets/...js` in DevTools:

1. Confirm `vite.config.ts` has `base: '/frontend/dist/'`
2. Confirm `dist/index.html` references `/frontend/dist/assets/index-XXX.js` (not `/assets/...`)
3. Confirm nginx has the `location /frontend/dist/assets/` block

Rebuild + reload nginx:
```bash
ssh hexugo-deploy "cd /var/www/hexugo/current/public/frontend && npm run build"
ssh hexugo "sudo systemctl reload nginx"
```

## Performance / bundle size

Current build:

| File | Raw | gzip |
|---|---|---|
| `index.html` | 0.46 KB | 0.31 KB |
| `index-XXXX.css` | 118 KB | 18.5 KB |
| `index-XXXX.js` | 449 KB | 128.5 KB |

That JS bundle is large because `@mui/material` + `@mui/icons-material` + `recharts` + tons of Radix primitives. Future optimization candidates:

1. Code-split per route: `const HomePage = lazy(() => import('./pages/HomePage'))`
2. Replace MUI with already-present Radix where possible
3. Tree-shake icon imports: `import { Heart } from 'lucide-react'` (already done) instead of `import * as Icons from '...'`
4. Consider Brotli compression at nginx (currently gzip only)
