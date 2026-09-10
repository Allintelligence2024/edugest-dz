#!/bin/bash
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# EduGest DZ — Smoke Test Script
# Usage: ./smoke-test.sh [BASE_URL]
# Default BASE_URL: http://127.0.0.1:8000
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8000}"
API="${BASE_URL}/api/v1"
PASS=0
FAIL=0

green() { printf "\033[32m✓ %s\033[0m\n" "$1"; }
red()   { printf "\033[31m✗ %s\033[0m\n" "$1"; }

check() {
    local label="$1" url="$2" expect="${3:-200}"
    status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null || true)
    if [ "$status" = "$expect" ]; then
        green "${label} → ${status}"
        PASS=$((PASS + 1))
    else
        red "${label} → ${status} (expected ${expect})"
        FAIL=$((FAIL + 1))
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Smoke Tests — ${BASE_URL}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check "GET /api/v1/health"          "${API}/health"          200
check "GET /api/v1/health/ping"     "${API}/health/ping"     200
check "GET /nonexistent-route"      "${API}/nonexistent"     404

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Results: ${PASS} passed, ${FAIL} failed"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exit $FAIL
