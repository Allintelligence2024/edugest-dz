#!/bin/sh
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:80}"
PASS=0
FAIL=0

green()  { printf '  \033[32m✓\033[0m %s\n' "$1"; }
red()    { printf '  \033[31m✗\033[0m %s\n' "$1"; }

check() {
    local label="$1" url="$2" expected="$3"
    local status
    status=$(curl -s -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || echo "000")
    if [ "$status" = "$expected" ]; then
        green "$label ($status)"
        PASS=$((PASS + 1))
    else
        red "$label → attendu $expected, reçu $status"
        FAIL=$((FAIL + 1))
    fi
}

echo "╔══════════════════════════════════════╗"
echo "║     EduGest DZ — Smoke Tests         ║"
echo "╚══════════════════════════════════════╝"
echo ""

check "Health check"               "$BASE_URL/api/health"           200
check "Login (mauvais email)"      "$BASE_URL/api/v1/auth/login"    401
check "Validation email requis"    "$BASE_URL/api/v1/auth/login"    422
check "Documentation Swagger"      "$BASE_URL/api/documentation"    200

echo ""
echo "╔══════════════════════════════════════╗"
echo "║  $PASS passed, $FAIL failed"
echo "╚══════════════════════════════════════╝"

exit $FAIL
