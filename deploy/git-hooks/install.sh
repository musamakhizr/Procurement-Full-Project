#!/usr/bin/env bash
# install.sh — Install repo git hooks for every developer.
#
# Run once after cloning:
#   bash deploy/git-hooks/install.sh
#
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
HOOKS_SRC="$REPO_ROOT/deploy/git-hooks"
HOOKS_DST="$REPO_ROOT/.git/hooks"

mkdir -p "$HOOKS_DST"
for hook in pre-commit; do
    src="$HOOKS_SRC/$hook"
    dst="$HOOKS_DST/$hook"
    [ -f "$src" ] || continue
    cp "$src" "$dst"
    chmod +x "$dst"
    echo "✓ installed: .git/hooks/$hook"
done

echo
echo "Hooks installed. They will block commits that try to add generated/secret files."
