# 02 · Server Provisioning Runbook

How to bring a fresh Ubuntu 24.04 LTS box up to a running Hexugo production server. The scripts in `deploy/scripts/` are idempotent — re-running them is safe.

## Prerequisites

- Ubuntu 24.04 LTS server (ARM64 or x86_64, 4 vCPU + 8GB RAM minimum)
- Public IP with cloud-provider firewall opened for `22`, `80`, `443`, and (optionally) `2222`
- Domain `hexugo.com` (and `www.hexugo.com`) DNS A-records pointed at the IP
- SSH access as `root` (initial)

## Phase 0 — Initial SSH key bootstrap

On your **local** machine:

```bash
# Generate a dedicated deploy keypair
ssh-keygen -t ed25519 -f ~/.ssh/hexugo_deploy -N "" -C "deploy@hexugo.com"

# Push pubkey to the server's root account (one-time, password auth)
ssh-copy-id -i ~/.ssh/hexugo_deploy.pub root@<SERVER_IP>

# Add SSH config alias
cat >> ~/.ssh/config <<EOF
Host hexugo
    HostName <SERVER_IP>
    Port 22
    User root
    IdentityFile ~/.ssh/hexugo_deploy
    StrictHostKeyChecking no

Host hexugo-deploy
    HostName <SERVER_IP>
    Port 22
    User deploy
    IdentityFile ~/.ssh/hexugo_deploy
EOF

# Verify
ssh hexugo "echo ok"
```

## Phase 1 — Pull deploy scripts

On the server:

```bash
ssh hexugo
git clone https://github.com/musamakhizr/Procurement-Full-Project.git /opt/hexugo-deploy
ln -sfn /opt/hexugo-deploy/deploy/templates /opt/hexugo-deploy/templates
ln -sfn /opt/hexugo-deploy/deploy/scripts   /opt/hexugo-deploy/scripts
cd /opt/hexugo-deploy/deploy/scripts
```

## Phase 2 — System bootstrap

Installs nginx, PHP 8.3 + extensions, Composer, MariaDB, Redis, Supervisor, Node 20, Certbot, fail2ban, UFW, unattended-upgrades.

```bash
sudo bash 01-bootstrap.sh
```

**Time:** ~3-5 minutes. Verify versions are printed at the end.

## Phase 3 — Deploy user + SSH hardening

Creates `deploy` user (sudoers NOPASSWD for whitelisted commands only), installs your pubkey, hardens sshd.

> **Important — Ubuntu 24.04 socket-activation gotcha**
> Ubuntu 24.04 sshd is socket-activated by default. The script disables `ssh.socket` and switches to standalone `ssh.service`, otherwise the `Port` directive in sshd_config is ignored and you get locked out.

```bash
DEPLOY_PUBKEY="$(cat ~/.ssh/hexugo_deploy.pub)" sudo -E bash 02-user-and-ssh.sh
```

After running:
```bash
# disable socket activation, enable service standalone
sudo systemctl disable --now ssh.socket
sudo systemctl enable --now ssh.service
```

> **About SSH ports:** The Hexugo server listens on **both `:22` and `:2222`** (defense-in-depth + compatibility for other users). UFW allows both. fail2ban watches both for brute-force.

## Phase 4 — Firewall + fail2ban

```bash
sudo bash 03-firewall.sh
```

This enables UFW with `deny incoming, allow outgoing` and opens `22`, `2222`, `80`, `443`. Configures fail2ban jails for sshd and nginx.

## Phase 5 — Database

```bash
DB_NAME=hexugo_prod \
DB_USER=hexugo_user \
DB_PASS='<random-strong-pw>' \
MYSQL_ROOT_PASS='<random-strong-pw>' \
sudo -E bash 04-database.sh
```

Hardens MariaDB (drops anonymous users / test db), creates app DB and user. **Save the passwords** — they go into `deploy/CREDENTIALS.local`.

> **Gotcha:** the script grants `<user>@'localhost'` only. Laravel uses TCP `127.0.0.1`, so you also need:
> ```sql
> CREATE USER 'hexugo_user'@'127.0.0.1' IDENTIFIED BY '<DB_PASS>';
> GRANT ALL PRIVILEGES ON hexugo_prod.* TO 'hexugo_user'@'127.0.0.1';
> FLUSH PRIVILEGES;
> ```

## Phase 6 — App bootstrap (first deploy)

Clones repo, installs composer deps (no-dev), generates APP_KEY, runs migrations + seed, builds frontend, sets permissions, points `current` symlink.

```bash
REPO_URL=https://github.com/musamakhizr/Procurement-Full-Project.git \
DB_NAME=hexugo_prod \
DB_USER=hexugo_user \
DB_PASS='<DB_PASS>' \
sudo -E bash 05-app-bootstrap.sh
```

## Phase 7 — Nginx + SSL

Installs PHP-FPM dedicated pool, OPcache config, nginx server block. Obtains LE cert via webroot challenge, then activates HTTPS server block.

```bash
LE_EMAIL=you@example.com sudo -E bash 06-nginx-ssl.sh
```

After this, `https://hexugo.com` should serve the React SPA. Certbot auto-renews via `certbot.timer`.

## Phase 8 — Supervisor (queue workers + scheduler)

```bash
sudo bash 07-supervisor.sh
```

Verify: `supervisorctl status` should show 2 `hexugo-queue` workers and 1 `hexugo-schedule` running.

## Phase 9 — Logrotate

```bash
sudo bash 08-logrotate.sh
```

## Phase 10 — phpMyAdmin (optional)

```bash
DEBIAN_FRONTEND=noninteractive sudo apt-get install -y phpmyadmin apache2-utils
sudo htpasswd -c /etc/nginx/.htpasswd-pma <pma_user>
sudo chmod 640 /etc/nginx/.htpasswd-pma
sudo chown root:www-data /etc/nginx/.htpasswd-pma
# nginx server block already includes the location - just reload
sudo systemctl reload nginx
```

Access at `https://hexugo.com/dbmgr-<random-suffix>/` — the suffix obscures the URL from drive-by scanners.

## Verification checklist

After provisioning, all of these should pass:

```bash
# Service status
ssh hexugo "systemctl is-active nginx php8.3-fpm mariadb redis-server supervisor fail2ban ssh"

# Listening ports (expect: 22, 2222, 80, 443, 3306-localhost, 6379-localhost)
ssh hexugo "ss -tlnp | awk '{print \$4}' | sort -u"

# UFW
ssh hexugo "ufw status verbose"

# Workers
ssh hexugo "supervisorctl status"

# HTTP smoke
curl -fsSI https://hexugo.com/
curl -fsS  https://hexugo.com/api/categories | head -c 200
```
