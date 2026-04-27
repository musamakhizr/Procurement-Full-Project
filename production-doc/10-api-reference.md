# 10 · API Reference

Base URL: **`https://hexugo.com/api`**

All requests:
- Send `Accept: application/json`
- Authenticated routes: `Authorization: Bearer <token>`
- Errors return `{"message": "...", ...}` with the appropriate HTTP status

## Verified working (2026-04-27)

| Method | Path | Auth | Status | Notes |
|---|---|---|---|---|
| POST | `/auth/register` | public | ✅ | Returns `{token, user}` |
| POST | `/auth/login` | public | ✅ 200 | Returns `{token, user}`; rate-limited to 5r/s |
| GET  | `/auth/me` | bearer | ✅ 200 | Returns current user |
| POST | `/auth/logout` | bearer | ✅ 200 | Revokes the current token |
| GET  | `/categories` | public | ✅ 200 | Returns category tree |
| GET  | `/products` | public | ✅ 200 | Paginated product list |
| GET  | `/products/{id}` | public | ✅ 200 | Single product detail |
| GET  | `/dashboard` | bearer | ✅ 200 | Summary + activity for current user |
| GET  | `/procurement-list` | bearer | ✅ 200 | User's "cart" |
| POST | `/procurement-list` | bearer | ✅ 201 | `{product_id, quantity?}` |
| PATCH| `/procurement-list/{id}` | bearer | ✅ | `{quantity}` |
| DELETE| `/procurement-list/{id}` | bearer | ✅ | Remove item |
| GET  | `/sourcing-requests` | bearer | ✅ 200 | List user's requests |
| POST | `/sourcing-requests` | bearer | ✅ 201 | Create new sourcing request |
| GET  | `/admin/product-stats` | admin | ✅ 200 | Counts by status |
| GET  | `/admin/products` | admin | ✅ 200 | All products (admin view) |
| POST | `/admin/products` | admin | ✅ | Create product |
| PATCH| `/admin/products/{id}` | admin | ✅ | Update product |
| DELETE| `/admin/products/{id}` | admin | ✅ | Delete product |
| GET  | `/admin/*` (as non-admin) | bearer | ✅ 403 | Authz works |
| ANY  | (no token) | bearer-required | ✅ 401 | After bootstrap/app.php fix |

## Examples

### Login

```bash
curl -X POST https://hexugo.com/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

Response:
```json
{
  "message": "Signed in successfully.",
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "organization_name": "Bright Future School",
    "organization_type": "school",
    "role": "customer",
    "is_admin": false
  },
  "token": "2|0jGxnsGEuKXFyWfjTz7412KFb5Pv..."
}
```

### Authenticated request

```bash
TOKEN=2|...
curl https://hexugo.com/api/dashboard \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

### Admin login (seeded user)

```bash
curl -X POST https://hexugo.com/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@procurepro.test","password":"password"}'
```

Then use the token to access `/admin/*` routes.

### Create a sourcing request

```bash
curl -X POST https://hexugo.com/api/sourcing-requests \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "type": "custom",
    "title": "School branded notebooks",
    "details": "Need 500 soft-touch covers, 200 pages",
    "quantity": 500,
    "budget_text": "$2000-3000",
    "delivery_date": "2026-05-30"
  }'
```

### Add item to procurement list

```bash
curl -X POST https://hexugo.com/api/procurement-list \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"product_id": 5, "quantity": 10}'
```

## Rate limits

| Endpoint | Zone | Sustained | Burst |
|---|---|---|---|
| `/api/auth/login` | `api_login` | 5/s | 10 |
| `/api/*` (other) | `api_general` | 60/s | 120 |

When exceeded, nginx returns `503` (with body "503 Service Temporarily Unavailable"). Repeated trigger → fail2ban ban via `nginx-limit-req` jail.

## Error response shape

| HTTP | Body |
|---|---|
| 401 Unauthenticated | `{"message":"Unauthenticated."}` |
| 403 Forbidden (non-admin) | `{"message":"Forbidden."}` (or similar — depends on the gate) |
| 404 | `{"message":"Not Found."}` (Laravel default) |
| 422 Validation | `{"message":"...","errors":{"field":["msg"]}}` |
| 429 Too Many Requests | (App-level throttling — app does not set this currently; nginx-side returns 503) |
| 500 | `{"message":"Server Error"}` (with full HTML page if `APP_DEBUG=true`, which we keep off) |
| 503 (nginx rate) | nginx HTML page |
| 503 (maintenance) | `errors::503` blade view |

## Seeded test accounts

| Email | Password | Role |
|---|---|---|
| `test@example.com` | `password` | customer |
| `admin@procurepro.test` | `password` | admin |

> **Production hardening TODO**: change these passwords or remove the seeder users in production. They exist purely for smoke testing — never share these creds beyond your team.

## Ad-hoc smoke test (quick "is the API alive?")

Save as `production-doc/scripts/smoke-api.sh` and run after each deploy:

```bash
B=https://hexugo.com/api
pass(){ printf "%-50s %s\n" "$1" "$2"; }

curl -fsS $B/categories > /dev/null && pass "categories" OK
curl -fsS $B/products | grep -q '"data"' && pass "products" OK
LOGIN=$(curl -fsS -X POST $B/auth/login -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}')
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]+"' | cut -d'"' -f4)
[ -n "$TOKEN" ] && pass "auth login" OK
curl -fsS -H "Authorization: Bearer $TOKEN" $B/auth/me > /dev/null && pass "auth me" OK
curl -fsS -H "Authorization: Bearer $TOKEN" $B/dashboard > /dev/null && pass "dashboard" OK
echo "DONE"
```
