#!/bin/bash
# ============================================================
# EduGest DZ — Restaurer un backup PostgreSQL
# Usage : ./scripts/restore-backup.sh backups/edugestdz_2026-07-01.sql.gz
# ============================================================

set -e

BACKUP_FILE=$1

if [ -z "$BACKUP_FILE" ]; then
    echo "❌ Usage : $0 <fichier_backup.sql.gz>"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Fichier non trouvé : $BACKUP_FILE"
    exit 1
fi

echo "⚠️  ATTENTION : Cette opération va écraser la base de données actuelle."
read -p "Continuer ? (oui/non) : " confirm
if [ "$confirm" != "oui" ]; then
    echo "Annulé."
    exit 0
fi

echo "🔄 Restauration en cours..."

docker-compose -f docker-compose.prod.yml stop app

gunzip -c "$BACKUP_FILE" | docker exec -i edugest_postgres \
    psql -U "${DB_USERNAME:-edugest_user}" -d "${DB_DATABASE:-edugestdz}"

docker-compose -f docker-compose.prod.yml start app

echo "✅ Restauration terminée depuis $BACKUP_FILE"
