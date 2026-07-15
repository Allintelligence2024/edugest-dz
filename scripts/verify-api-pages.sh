#!/bin/bash
# verify-api-pages.sh — Vérifie que les pages API principales retournent des données
# Usage: ./scripts/verify-api-pages.sh http://localhost:8000

set -euo pipefail

BASE_URL="${1:-http://localhost:8000}"
API="${BASE_URL}/api/v1"

echo "🔍 Vérification des API EduGest DZ — ${API}"
echo ""

# Auth
echo "1. Login admin..."
TOKEN=$(curl -s -X POST "${API}/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edugest-demo.dz","password":"password"}' \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "   ❌ Login échoué"
  exit 1
fi
echo "   ✅ Token obtenu"

AUTH="Authorization: Bearer ${TOKEN}"
TENANT="X-Tenant-ID: $(curl -s "${API}/auth/me" -H "$AUTH" | grep -o '"tenant_id":"[^"]*"' | head -1 | cut -d'"' -f4)"

# Endpoints
declare -A ENDPOINTS=(
  ["Dashboard analytics"]="/analytics/dashboard"
  ["Eleves list"]="/eleves?per_page=5"
  ["Notes list"]="/notes?per_page=5"
  ["Planning seances"]="/seances?per_page=5"
  ["Absences list"]="/absences?per_page=5"
  ["Enseignants list"]="/enseignants?per_page=5"
  ["Factures list"]="/factures?per_page=5"
  ["Groupes list"]="/groupes?per_page=5"
  ["Matieres list"]="/matieres"
  ["Salles list"]="/salles"
  ["Onboarding statut"]="/onboarding"
  ["Modules list"]="/modules"
  ["Modules actifs"]="/modules/actifs"
)

PASSED=0
FAILED=0

for NAME in "${!ENDPOINTS[@]}"; do
  PATH_URL="${ENDPOINTS[$NAME]}"
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${API}${PATH_URL}" -H "$AUTH" -H "$TENANT")
  
  if [ "$STATUS" -ge 200 ] && [ "$STATUS" -lt 300 ]; then
    echo "   ✅ ${NAME} — ${STATUS}"
    ((PASSED++))
  else
    echo "   ❌ ${NAME} — ${STATUS} (${PATH_URL})"
    ((FAILED++))
  fi
done

echo ""
echo "📊 Résultat: ${PASSED} passés, ${FAILED} échoués"

if [ "$FAILED" -gt 0 ]; then
  exit 1
fi

echo "✅ Toutes les API sont fonctionnelles !"
