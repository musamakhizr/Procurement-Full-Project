#!/usr/bin/env bash
# deploy-now.sh — Run from your local machine after `git push`.
#
# Pulls latest main on hexugo.com, rebuilds, reloads, restarts workers.
# Works for anyone with the deploy SSH key — no ~/.ssh/config alias required.
#
# First-time setup (any team member):
#   1. Get the private key file `hexugo_deploy` from the team password vault.
#   2. Place it at:  ~/.ssh/hexugo_deploy        (Linux/Mac/Git-Bash)
#                or  %USERPROFILE%\.ssh\hexugo_deploy   (Windows)
#   3. Lock it down (Linux/Mac):  chmod 600 ~/.ssh/hexugo_deploy
#   4. Run this script: bash deploy/deploy-now.sh
#
# Usage:
#   bash deploy/deploy-now.sh                     # deploys main
#   BRANCH=hotfix/x bash deploy/deploy-now.sh     # different branch
#   KEY=~/keys/my-key bash deploy/deploy-now.sh   # different key file
#
set -euo pipefail

BRANCH="${BRANCH:-main}"
SSH_HOST_IP="${SSH_HOST_IP:-47.86.173.194}"
SSH_PORT="${SSH_PORT:-2222}"
SSH_USER="${SSH_USER:-deploy}"
KEY="${KEY:-$HOME/.ssh/hexugo_deploy}"

if [ ! -f "$KEY" ]; then
    echo "ERROR: SSH key not found at: $KEY"
    echo "Get the 'hexugo_deploy' key from the team vault and put it there."
    echo "Or set: KEY=/path/to/your/hexugo_deploy bash deploy/deploy-now.sh"
    exit 1
fi

echo "▶ Deploying $BRANCH to $SSH_USER@$SSH_HOST_IP:$SSH_PORT"
ssh -t \
    -i "$KEY" \
    -p "$SSH_PORT" \
    -o StrictHostKeyChecking=accept-new \
    -o ServerAliveInterval=30 \
    "$SSH_USER@$SSH_HOST_IP" \
    "BRANCH=$BRANCH bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"
