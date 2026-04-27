# 09 · Monitoring, Logs & Errors

## Where logs live

| Source | Path | Rotation |
|---|---|---|
| Laravel app | `/var/www/hexugo/shared/storage/logs/laravel-YYYY-MM-DD.log` | daily, 14d (logrotate) |
| Queue workers | `/var/www/hexugo/shared/storage/logs/queue-worker.log` | daily, 14d |
| Scheduler | `/var/www/hexugo/shared/storage/logs/scheduler.log` | daily, 14d |
| nginx access (Hexugo) | `/var/log/nginx/hexugo.access.log` | weekly (default) |
| nginx error (Hexugo) | `/var/log/nginx/hexugo.error.log` | weekly |
| PHP-FPM hexugo error | `/var/log/php8.3-fpm-hexugo.error.log` | weekly, 8 (logrotate) |
| PHP-FPM hexugo slowlog | `/var/log/php8.3-fpm-hexugo.slow.log` | weekly, 8 |
| MariaDB slow query | `/var/log/mysql/slow.log` | weekly (default) |
| sshd | `journalctl -u ssh` | systemd-journald |
| fail2ban | `journalctl -u fail2ban` + `/var/log/fail2ban.log` | weekly |
| UFW | `/var/log/ufw.log` | weekly |
| Certbot | `/var/log/letsencrypt/letsencrypt.log` | weekly |
| Supervisor | `/var/log/supervisor/supervisord.log` | weekly |

`logrotate` config: `/etc/logrotate.d/hexugo`.

## Quick "what's wrong?" commands

```bash
ssh hexugo-deploy "tail -200 /var/www/hexugo/shared/storage/logs/laravel-$(date +%F).log"
ssh hexugo "sudo tail -100 /var/log/nginx/hexugo.error.log"
ssh hexugo "sudo tail -100 /var/log/php8.3-fpm-hexugo.error.log"
ssh hexugo "sudo journalctl -u ssh -n 50 --no-pager"
ssh hexugo "sudo journalctl -u fail2ban -n 50 --no-pager"
```

## Service health one-liner

```bash
ssh hexugo "for s in nginx php8.3-fpm mariadb redis-server supervisor fail2ban ssh; do
  printf '%-20s %s\n' \"\$s\" \"\$(systemctl is-active \$s)\"
done && supervisorctl status"
```

## Real-time tailing

```bash
# Laravel app log
ssh hexugo-deploy "tail -f /var/www/hexugo/shared/storage/logs/laravel-$(date +%F).log"

# nginx access (color-coded by status)
ssh hexugo "sudo tail -f /var/log/nginx/hexugo.access.log"

# Queue worker
ssh hexugo "sudo supervisorctl tail -f hexugo-queue:hexugo-queue_00 stdout"
```

## Application metrics

> **TODO** — no metrics agent installed. Recommended additions:

| Tool | Purpose | Install |
|---|---|---|
| Netdata | per-host real-time metrics dashboard | `bash <(curl -SsL https://my-netdata.io/kickstart.sh)` |
| Sentry | error tracking + release tagging | Add `SENTRY_LARAVEL_DSN` to `.env`, install `composer require sentry/sentry-laravel` |
| Telegraf + InfluxDB / Prometheus + Grafana | long-term time-series | bigger lift |

The `.env` already has slots:
```ini
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
```

## Sentry quick-setup (when you're ready)

```bash
# 1. Add SDK
ssh hexugo-deploy "cd /var/www/hexugo/current && composer require sentry/sentry-laravel"
# 2. Publish config
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan vendor:publish --provider=\"Sentry\\Laravel\\ServiceProvider\""
# 3. Edit shared .env, set SENTRY_LARAVEL_DSN=...
ssh hexugo-deploy "$EDITOR /var/www/hexugo/shared/.env"
# 4. Install handler in bootstrap/app.php (per Sentry docs for Laravel 12)
# 5. Test
ssh hexugo-deploy "cd /var/www/hexugo/current && php artisan sentry:test"
```

## fail2ban activity

```bash
# All jails
ssh hexugo "sudo fail2ban-client status"

# Specific jail
ssh hexugo "sudo fail2ban-client status sshd"

# Recently banned IPs
ssh hexugo "sudo zgrep 'Ban' /var/log/fail2ban.log* | awk '{print \$NF}' | sort | uniq -c | sort -rn | head -20"

# Manually unban
ssh hexugo "sudo fail2ban-client set sshd unbanip 1.2.3.4"
```

## Disk / RAM / load

```bash
ssh hexugo "free -h && echo --- && df -hT / && echo --- && uptime && echo --- && top -bn1 | head -15"
```

## Disk pressure check

```bash
ssh hexugo "du -sh /var/www/hexugo/* /var/log /var/lib/mysql /var/lib/redis /home 2>/dev/null"
```

If `/var/www/hexugo/releases/` grows: deploy script keeps last 3 by default. Increase pruning:
```bash
ssh hexugo-deploy "ls -1dt /var/www/hexugo/releases/*/ | tail -n +4 | xargs -r rm -rf"
```

## Common alerts to consider setting up

| Signal | Where | Threshold |
|---|---|---|
| `5xx` rate from nginx | `hexugo.access.log` | > 1% over 5min |
| Queue depth | `redis-cli llen 'queues:default'` | > 1000 backlog |
| MariaDB connections | `SHOW STATUS LIKE 'Threads_connected'` | > 150 (of 200 max) |
| Free disk on `/` | `df` | < 15% |
| Cert expiry | `openssl x509 -enddate -noout` | < 14d |
| fail2ban currently banning | `fail2ban-client status sshd` | > 50 IPs (might mean attack) |

A simple cron + curl-to-Slack/Discord would suffice as a first version of alerting. Defer to a proper monitoring tool once usage warrants it.
