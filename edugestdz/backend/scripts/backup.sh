#!/bin/bash
# Sauvegarde PostgreSQL au format custom pg_dump (restauration via
# pg_restore — voir scripts/restore-backup.sh à la racine du dépôt).
# NB : bash est requis (set -o pipefail n'existe pas en POSIX sh).
set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-/backups}"
DB_NAME="${DB_DATABASE:-edugestdz}"
DB_USER="${DB_USERNAME:-edugest_user}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
S3_BUCKET="${S3_BUCKET:-}"

mkdir -p "$BACKUP_DIR"

FILENAME="edugest_${DB_NAME}_${TIMESTAMP}.dump"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

echo "[$(date)] Début sauvegarde $DB_NAME..."

PGPASSWORD="$DB_PASSWORD" pg_dump \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    --no-owner \
    --no-acl \
    --compress=9 \
    --format=custom \
    --file="$FILEPATH" \
    2>&1

echo "[$(date)] Backup créé : $FILEPATH ($(du -h "$FILEPATH" | cut -f1))"

if [ -n "$S3_BUCKET" ]; then
    echo "[$(date)] Envoi vers S3..."
    aws s3 cp "$FILEPATH" "s3://${S3_BUCKET}/backups/${DB_NAME}/${FILENAME}" --only-show-errors
    echo "[$(date)] Envoi S3 terminé"
fi

echo "[$(date)] Nettoyage backups > ${RETENTION_DAYS} jours..."
find "$BACKUP_DIR" -name "edugest_${DB_NAME}_*.dump" -mtime "+${RETENTION_DAYS}" -delete

echo "[$(date)] ✓ Sauvegarde terminée"
