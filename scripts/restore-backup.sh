#!/bin/bash
# ============================================================
# EduGest DZ — Restaurer un backup PostgreSQL
#
# Formats reconnus (détection par signature binaire, pas par
# extension — les anciens fichiers « .sql.gz.dump » mal nommés
# sont ainsi rattrapés) :
#   .sql.gz — SQL « plain » gzippé (sidecar Docker `backup`,
#             image prodrigestivill/postgres-backup-local) ;
#   .dump   — format custom pg_dump (backend/scripts/backup.sh,
#             `make backup` côté backend).
#
# Usage :
#   ./scripts/restore-backup.sh backups/edugestdz_20260910_020000.sql.gz
#   ./scripts/restore-backup.sh backups/edugest_edugestdz_20260910_020000.dump --yes
#
# Le schéma public est écrasé (DROP SCHEMA ... CASCADE) : la
# base actuelle est remplacée par le contenu du backup. Les
# services app, queue et scheduler sont arrêtés pendant
# l'opération puis relancés — y compris en cas d'échec (trap).
# Les variables DB_DATABASE / DB_USERNAME sont lues depuis
# l'environnement, ou depuis .env s'il existe (l'environnement
# a priorité, comme docker-compose).
# ============================================================

set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
DB_NAME="${DB_DATABASE:-edugestdz}"
DB_USER="${DB_USERNAME:-edugest_user}"
PG_CONTAINER="${PG_CONTAINER:-edugestdz_postgres}"
APP_SERVICES="app queue scheduler"

BACKUP_FILE="${1:-}"
ASSUME_YES=false
for arg in "${@:2}"; do
    case "$arg" in
        -y|--yes) ASSUME_YES=true ;;
        *)
            echo "❌ Option inconnue : $arg" >&2
            exit 1
            ;;
    esac
done

usage() {
    echo "Usage : $0 <fichier_backup.sql.gz|.dump> [--yes]"
    echo ""
    echo "  --yes  ne pas demander de confirmation (usage scripté)"
}

# Charger .env sans écraser les variables déjà positionnées
# (même précédence que docker-compose : l'environnement gagne).
if [ -f .env ]; then
    while IFS='=' read -r k v; do
        case "$k" in ''|\#*) continue ;; esac
        v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"
        if [ -z "${!k:-}" ]; then
            export "$k=$v"
        fi
    done < .env
    # réévaluer les valeurs par défaut après .env
    DB_NAME="${DB_DATABASE:-edugestdz}"
    DB_USER="${DB_USERNAME:-edugest_user}"
fi

if [ -z "$BACKUP_FILE" ]; then
    usage
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Fichier non trouvé : $BACKUP_FILE" >&2
    exit 1
fi

if [ ! -s "$BACKUP_FILE" ]; then
    echo "❌ Fichier vide : $BACKUP_FILE" >&2
    exit 1
fi

command -v docker >/dev/null 2>&1 || {
    echo "❌ Commande docker introuvable." >&2
    exit 1
}
command -v docker-compose >/dev/null 2>&1 || {
    echo "❌ Commande docker-compose introuvable." >&2
    exit 1
}

# ── Détection du format par signature binaire ──────────────
#   gzip          : 1f 8b
#   pg_dump custom : « PGDMP » (50 47 44 4d 50)
magic="$(head -c 5 "$BACKUP_FILE" | od -An -tx1 | tr -d ' \n')"
case "$magic" in
    1f8b*)       FORMAT="plain" ;;
    5047444d50*) FORMAT="custom" ;;
    *)
        echo "❌ Format non reconnu (ni gzip, ni dump custom pg_dump) : $BACKUP_FILE" >&2
        exit 1
        ;;
esac

if [ "$FORMAT" = "plain" ]; then
    FORMAT_LABEL="SQL plain gzippé (pg_dump | gzip)"
else
    FORMAT_LABEL="custom pg_dump (pg_restore)"
fi

echo "📋 Fichier   : $BACKUP_FILE"
echo "   Format    : $FORMAT_LABEL"
echo "   Base      : $DB_NAME (conteneur $PG_CONTAINER)"
echo ""
echo "⚠️  ATTENTION : le schéma public actuel sera ÉCRASÉ (DROP CASCADE)."
if [ "$ASSUME_YES" != "true" ]; then
    if ! read -r -p "Continuer ? (oui/non) : " confirm; then
        echo "❌ Entrée standard fermée — utilisez --yes pour un usage scripté." >&2
        exit 1
    fi
    if [ "$confirm" != "oui" ]; then
        echo "Annulé."
        exit 0
    fi
fi

# ── Arrêt des services qui accèdent à la base ─────────────
start_services() {
    docker-compose -f "$COMPOSE_FILE" start $APP_SERVICES >/dev/null 2>&1 || true
}
trap start_services EXIT

echo "⏸  Arrêt des services : $APP_SERVICES..."
docker-compose -f "$COMPOSE_FILE" stop $APP_SERVICES >/dev/null

echo "🔄 Restauration en cours..."

# ── Écrasement du schéma existant ─────────────────────────
docker exec -i "$PG_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" \
    -v ON_ERROR_STOP=1 \
    -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public;'

# ── Restauration selon le format détecté ──────────────────
if [ "$FORMAT" = "custom" ]; then
    docker exec -i "$PG_CONTAINER" pg_restore \
        -U "$DB_USER" \
        --dbname="$DB_NAME" \
        --no-owner \
        --no-acl \
        --exit-on-error \
        < "$BACKUP_FILE"
else
    gunzip -c "$BACKUP_FILE" | docker exec -i "$PG_CONTAINER" psql \
        -U "$DB_USER" \
        -d "$DB_NAME" \
        -v ON_ERROR_STOP=1
fi

# Statistiques à jour après restauration (best effort)
docker exec -i "$PG_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" \
    -c 'ANALYZE;' >/dev/null 2>&1 || true

echo "✅ Restauration terminée depuis $BACKUP_FILE"
