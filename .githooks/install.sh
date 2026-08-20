#!/usr/bin/env bash
#
# Points this clone's git hooks at the tracked .githooks/ directory, so the pre-push hook
# is versioned with the repository instead of living untracked in .git/hooks.
#
# Run once per clone:   bash .githooks/install.sh
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

git config core.hooksPath .githooks
chmod +x .githooks/pre-push 2>/dev/null || true
chmod +x .github/scripts/detect-services.sh 2>/dev/null || true

echo "core.hooksPath -> $(git config core.hooksPath)"
echo ""
echo "Installed. From now on 'git push' runs the tests of every affected service first,"
echo "and cancels the push if any of them fail."
echo ""
echo "  git push --no-verify   push without running them"
echo "  SKIP_TESTS=1 git push  same thing"
echo ""
echo "To undo:  git config --unset core.hooksPath"
