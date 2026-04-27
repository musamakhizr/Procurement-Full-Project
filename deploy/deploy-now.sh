#!/usr/bin/env bash
# deploy-now.sh — Run from your local machine after `git push`.
#
# Pulls the latest main on hexugo.com, rebuilds, reloads, restarts workers.
# Streams the server output in real time so you see exactly what happens.
#
# Usage:
#   bash deploy/deploy-now.sh           # deploys main
#   BRANCH=hotfix/x bash deploy/deploy-now.sh
#
set -euo pipefail

BRANCH="${BRANCH:-main}"
SSH_HOST="${SSH_HOST:-hexugo-deploy}"

echo "▶ Deploying $BRANCH to $SSH_HOST"
ssh -t "$SSH_HOST" "BRANCH=$BRANCH bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"
