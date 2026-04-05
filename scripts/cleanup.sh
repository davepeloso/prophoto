#!/usr/bin/env bash
# =============================================================================
# ProPhoto Repo Cleanup Script
# Run this ONCE from your Mac terminal in the repo root:
#   cd ~/Sites/prophoto && bash scripts/cleanup.sh
# =============================================================================

set -e

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"
echo "Running cleanup in: $REPO_ROOT"
echo ""

# -----------------------------------------------------------------------------
# 0. Clear stale git lock if present (left by background git process)
# -----------------------------------------------------------------------------
if [ -f ".git/index.lock" ]; then
  echo "[0] Clearing stale .git/index.lock..."
  rm -f ".git/index.lock" && echo "  lock cleared." || echo "  could not remove lock — git may be running. Abort and try again."
fi

# -----------------------------------------------------------------------------
# 1. Remove OS / IDE junk files
# -----------------------------------------------------------------------------
echo "[1/6] Removing junk files (.DS_Store, .idea)..."
find . -name ".DS_Store" -not -path "./.git/*" -delete
find . -name ".idea" -type d -not -path "./.git/*" -exec rm -rf {} + 2>/dev/null || true
find . -name "*.iml" -not -path "./.git/*" -delete
echo "  done."

# -----------------------------------------------------------------------------
# 2. Remove tracked junk from git index (if any slipped through)
# -----------------------------------------------------------------------------
echo "[2/6] Untracking junk from git index..."
git rm -r --cached .DS_Store 2>/dev/null || true
git rm -r --cached .idea 2>/dev/null || true
git rm -r --cached */.idea 2>/dev/null || true
git rm -r --cached */.phpunit.result.cache 2>/dev/null || true
git rm -r --cached */.phpunit.cache 2>/dev/null || true
git rm -r --cached output 2>/dev/null || true
git rm -r --cached tmp 2>/dev/null || true
echo "  done."

# -----------------------------------------------------------------------------
# 3. Remove junk from archived legacy ingest
# -----------------------------------------------------------------------------
echo "[3/6] Cleaning _archive/prophoto-ingest-legacy..."
LEGACY="_archive/prophoto-ingest-legacy"
if [ -d "$LEGACY" ]; then
  rm -rf "$LEGACY/vendor"
  rm -rf "$LEGACY/dist"
  rm -rf "$LEGACY/node_modules"
  rm -rf "$LEGACY/.idea"
  rm -rf "$LEGACY/.phpunit.cache"
  echo "  done."
else
  echo "  skip: _archive/prophoto-ingest-legacy not found"
fi

# -----------------------------------------------------------------------------
# 4. Enforce package purity — remove vendor/node_modules/dist/.idea per package
# -----------------------------------------------------------------------------
echo "[4/6] Cleaning junk from active packages..."
PACKAGES=(
  prophoto-assets
  prophoto-booking
  prophoto-contracts
  prophoto-intelligence
  prophoto-gallery
  prophoto-invoicing
  prophoto-access
  prophoto-payments
  prophoto-settings
  prophoto-storage
  prophoto-tenancy
  prophoto-security
  prophoto-notifications
  prophoto-permissions
  prophoto-ai
  prophoto-audit
  prophoto-debug
  prophoto-downloads
  prophoto-interactions
)

for pkg in "${PACKAGES[@]}"; do
  if [ -d "$pkg" ]; then
    removed=""
    [ -d "$pkg/vendor" ]       && rm -rf "$pkg/vendor"       && removed="$removed vendor"
    [ -d "$pkg/node_modules" ] && rm -rf "$pkg/node_modules" && removed="$removed node_modules"
    [ -d "$pkg/dist" ]         && rm -rf "$pkg/dist"         && removed="$removed dist"
    [ -d "$pkg/.idea" ]        && rm -rf "$pkg/.idea"        && removed="$removed .idea"
    [ -f "$pkg/.DS_Store" ]    && rm -f  "$pkg/.DS_Store"    && removed="$removed .DS_Store"
    if [ -n "$removed" ]; then
      echo "  $pkg: removed$removed"
    fi
  fi
done

# Remove root-level junk dirs
[ -d "output" ] && rm -rf output && echo "  removed: output/"
[ -d "tmp" ]    && rm -rf tmp    && echo "  removed: tmp/"
[ -f "test_write_probe.txt" ] && rm -f test_write_probe.txt && echo "  removed: test_write_probe.txt"
echo "  done."

# -----------------------------------------------------------------------------
# 5. Remove root-level dev docs now that they're in docs/dev/
#    (safe to delete since copies exist in docs/dev/)
# -----------------------------------------------------------------------------
echo "[5/6] Removing root-level dev docs (moved to docs/dev/)..."
[ -f "DEV-MANUAL.md" ] && git rm -f DEV-MANUAL.md && echo "  removed: DEV-MANUAL.md"
[ -f "HANDOFF.md" ]    && git rm -f HANDOFF.md    && echo "  removed: HANDOFF.md"
[ -f "TODO.md" ]       && git rm -f TODO.md       && echo "  removed: TODO.md"
# Archive the RTF (copy exists at docs/archive/ — remove original from docs root)
if [ -f "docs/Core Platform Components.rtf" ]; then
  git rm -f "docs/Core Platform Components.rtf" && echo "  removed: docs/Core Platform Components.rtf (copy in docs/archive/)"
fi
echo "  done."

# -----------------------------------------------------------------------------
# 6. Commit all changes
# -----------------------------------------------------------------------------
echo "[6/6] Staging and committing..."
git add -A
git status --short
git commit -m "chore: repo cleanup — gitignore, archive legacy ingest, normalize docs, enforce package purity"

echo ""
echo "============================================================"
echo "  Cleanup complete. Repo is now in a clean state."
echo "============================================================"
git log --oneline -3
