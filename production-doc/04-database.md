# 04 · MariaDB & phpMyAdmin

## Server

| | |
|---|---|
| Engine | MariaDB 10.11.14 (LTS) |
| Bind   | `127.0.0.1:3306` (no remote) |
| Socket | `/run/mysqld/mysqld.sock` |
| Data dir | `/var/lib/mysql` |
| Slow log | `/var/log/mysql/slow.log` (queries > 2s) |
| Tuning   | `/etc/mysql/mariadb.conf.d/99-hexugo-tuning.cnf` |

### Tuning highlights (for 14GB RAM box)

| Setting | Value | Why |
|---|---|---|
| `innodb_buffer_pool_size` | 4G | Hot working set in RAM |
| `innodb_buffer_pool_instances` | 4 | Parallelism |
| `innodb_log_file_size` | 512M | Larger redo, fewer checkpoint pauses |
| `innodb_flush_log_at_trx_commit` | 2 | Slight durability tradeoff for throughput |
| `innodb_flush_method` | O_DIRECT | Bypass OS page cache (use bp_size instead) |
| `innodb_io_capacity` | 2000 | NVMe-class IO |
| `max_connections` | 200 | Sized for ~40 PHP-FPM workers + headroom |
| `query_cache_type` | 0 | Disabled (deprecated, contention) |
| `slow_query_log` | 1 | Enabled |
| `long_query_time` | 2s | Anything slower → log |

To re-tune after a server resize:
```bash
sudo $EDITOR /etc/mysql/mariadb.conf.d/99-hexugo-tuning.cnf
sudo systemctl restart mariadb
```

## Schema

| Table | Purpose |
|---|---|
| `users` | Customers + admins (with `role`, `organization_*` fields) |
| `categories` | Hierarchical product categories (parent_id self-ref) |
| `products` | Product catalog (one per SKU) |
| `product_price_tiers` | Quantity-based pricing per product |
| `procurement_list_items` | User's "cart" items |
| `sourcing_requests` | Custom sourcing requests by users |
| `sourcing_request_links` | Reference URLs attached to a request |
| `personal_access_tokens` | Sanctum tokens |
| `cache`, `cache_locks` | Laravel cache (DB store fallback) |
| `jobs`, `job_batches`, `failed_jobs` | Queue infrastructure (used only as fallback; live queue is Redis) |
| `sessions` | Web sessions (also fallback; live sessions in Redis) |
| `migrations` | Laravel migration ledger |

## App-level access

The Laravel app connects via TCP `127.0.0.1:3306` as `hexugo_user` (from `/var/www/hexugo/shared/.env`).

To inspect what's configured:
```bash
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan tinker --execute='dd(config(\"database.connections.mysql\"));'"
```

## Backups

> **TODO** — backups are not yet automated. Set up a daily `mysqldump` cron:

```bash
# /etc/cron.d/hexugo-db-backup
0 3 * * * root /usr/bin/mysqldump --single-transaction --routines --triggers \
  -u root hexugo_prod | gzip > /var/backups/hexugo-$(date +\%F).sql.gz && \
  find /var/backups -name 'hexugo-*.sql.gz' -mtime +30 -delete
```

A weekly off-site copy (S3 / OSS) is recommended.

## Manual queries

Always use `mysql -u root` (unix-socket auth from a root shell):

```bash
ssh hexugo
mariadb -u root hexugo_prod
```

Or as the app user:
```bash
mariadb -u hexugo_user -p hexugo_prod
# password from deploy/CREDENTIALS.local
```

## phpMyAdmin

URL: **`https://hexugo.com/dbmgr-4591d95d/`**

Two layers of auth:
1. **HTTP Basic Auth** (htpasswd) — initial gate
2. **phpMyAdmin cookie auth** — log in with `hexugo_user` / `root`

| | |
|---|---|
| Basic auth user | `hexadmin` |
| Basic auth pass | (in `deploy/CREDENTIALS.local` as `PMA_BASIC_PASS`) |
| Config file | `/etc/phpmyadmin/config.inc.php` |
| Document root | `/usr/share/phpmyadmin/` (symlinked under nginx alias) |
| Hidden DBs | `information_schema`, `mysql`, `performance_schema`, `sys` |

To **rotate the basic auth password**:
```bash
ssh hexugo "sudo htpasswd -B /etc/nginx/.htpasswd-pma hexadmin"
```

To **disable phpMyAdmin completely**:
```bash
ssh hexugo
sudo sed -i 's/^    location ^~ \/dbmgr-/#    location ^~ \/dbmgr-/' /etc/nginx/sites-available/hexugo.conf
sudo nginx -t && sudo systemctl reload nginx
```
(Then revert the server-side edit and update the repo's nginx template too.)

## Common operational tasks

### Find slow queries this week
```bash
ssh hexugo "sudo tail -200 /var/log/mysql/slow.log"
```

### Online schema changes
For tables under heavy write load, prefer `pt-online-schema-change` (Percona Toolkit). Not currently installed:
```bash
sudo apt-get install -y percona-toolkit
```

### Check replication / data growth
```bash
ssh hexugo "mariadb -u root -e \"
  SELECT table_schema 'db',
    ROUND(SUM(data_length + index_length)/1024/1024, 2) 'size_mb'
  FROM information_schema.tables
  GROUP BY table_schema
  ORDER BY size_mb DESC;\""
```

### Reset a user's password (admin emergency)

```bash
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan tinker --execute='
  \$u = App\Models\User::where(\"email\", \"someone@example.com\")->first();
  \$u->password = \"new-strong-password\";
  \$u->save();
  echo \"Reset OK\";'"
```
