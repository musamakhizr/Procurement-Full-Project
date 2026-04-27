#!/usr/bin/env bash
# 07-supervisor.sh — Install supervisor program for queue workers + scheduler
set -euo pipefail

echo ">> Installing supervisor config…"
install -m 644 /opt/hexugo-deploy/templates/supervisor-hexugo.conf /etc/supervisor/conf.d/hexugo.conf

echo ">> Reloading supervisor…"
supervisorctl reread
supervisorctl update
sleep 2
supervisorctl status

echo "<<< 07-supervisor.sh complete"
