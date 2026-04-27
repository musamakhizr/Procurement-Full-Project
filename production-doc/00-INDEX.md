# Hexugo Production Documentation

This directory contains the complete operational and architectural documentation for the **https://hexugo.com** production deployment.

## Reading order

| # | Document | Read when… |
|---|---|---|
| [01](./01-architecture.md) | **Architecture & topology** | You want to understand the high-level design |
| [02](./02-server-setup.md) | **Server provisioning runbook** | Provisioning a fresh Ubuntu 24.04 box from scratch |
| [03](./03-security.md) | **Security model** | Auditing the box, hardening, or onboarding new keys |
| [04](./04-database.md) | **MariaDB & phpMyAdmin** | DB tuning, backups, accessing phpMyAdmin |
| [05](./05-application.md) | **Laravel application config** | App-level configuration, .env, queues, scheduler |
| [06](./06-frontend.md) | **React/Vite frontend** | Building, asset paths, SPA routing |
| [07](./07-nginx-and-ssl.md) | **Nginx & TLS** | Web server, SSL renewal, headers, rate limits |
| [08](./08-deploy.md) | **Deploy & rollback** | Shipping a new release, rolling back |
| [09](./09-monitoring.md) | **Monitoring, logs & errors** | Where to look when things go wrong |
| [10](./10-api-reference.md) | **API endpoint reference** | Every backend route, with auth & sample payloads |
| [11](./11-runbook-incidents.md) | **Incident runbook** | Known failure modes & fixes (incl. SSH lockout recovery) |
| [12](./12-credentials.md) | **Credentials index** | Where each secret lives (no values in git) |

## Quick facts

| | |
|---|---|
| Domain | https://hexugo.com |
| Server IP | `47.86.173.194` (Alibaba Cloud, ARM64 Ubuntu 24.04) |
| GitHub repo | https://github.com/musamakhizr/Procurement-Full-Project |
| SSH ports | `22` and `2222` (key-only, no passwords) |
| Stack | Nginx 1.24 · PHP 8.3-FPM · Laravel 12 · MariaDB 10.11 · Redis 7 · Supervisor 4 · Node 20 + Vite 6 + React 18 |
| TLS | Let's Encrypt, auto-renew via `certbot.timer` |
| DB UI | https://hexugo.com/dbmgr-4591d95d/ (htpasswd + cookie auth) |

## Contributing

When the system changes, update the relevant doc **in the same commit** as the change. The `deploy/` directory is the source of truth for configuration; this directory is the source of truth for *understanding*.
