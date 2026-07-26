#!/bin/bash
set -euo pipefail

# =============================================================================
# backup-postgres.sh — Sauvegarde PostgreSQL EduGest DZ
# =============================================================================
#
# Fonctionnalités :
#   - pg_dump avec compression gzip
#   - Rotation automatique des anciennes sauvegardes
#   - Vérification d'intégrité (gzip -t)
#   - Logs structurés
#
# Usage :
#   ./scripts/backup-postgres.sh
#
# Variables d'environnement (optionnelles) :
#   DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#   BACKUP_DIR, RETENTION_DAYS
#
# =============================================================================

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-/var/backups/edugest}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-edugestdz}"
DB_USER="${DB_USERNAME:-edugest_user}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "$BACKUP_DIR"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=== Début sauvegarde PostgreSQL ==="
log "Base: ${DB_NAME} @ ${DB_HOST}:${DB_PORT}"
log "Utilisateur: ${DB_USER}"
log "Répertoire: ${BACKUP_DIR}"
log "Rétention: ${RETENTION_DAYS} jours"

# ── 1. pg_dump → gzip ──────────────────────────────────────────

DUMP_FILE="${BACKUP_DIR}/edugest_${DB_NAME}_${TIMESTAMP}.sql.gz"

log "Exécution pg_dump + gzip..."
PGPASSWORD="$DB_PASSWORD" pg_dump \
    -h "$DB_HOST" \
    -p "$DB_PORT" \
    -U "$DB_USER" \
    -d "$DB_NAME" \
    --no-owner \
    --no-acl \
    --format=plain \
    2>>"$LOG_FILE" | gzip -9 > "$DUMP_FILE"

DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
log "Dump créé: ${DUMP_FILE} (${DUMP_SIZE})"

# ── 2. Vérification intégrité gzip ──────────────────────────────

log "Vérification intégrité gzip..."
if gzip -t "$DUMP_FILE" 2>/dev/null; then
    log "✅ Intégrité gzip vérifiée"
else
    log "❌ ERREUR: Fichier gzip corrompu!"
    exit 1
fi

# ── 3. Vérification contenu SQL ─────────────────────────────────

log "Vérification contenu SQL..."
SQL_LINES=$(zcat "$DUMP_FILE" | wc -l)
if [ "$SQL_LINES" -gt 0 ]; then
    log "✅ Contenu SQL valide: ${SQL_LINES} lignes"
else
    log "❌ ERREUR: Fichier SQL vide!"
    exit 1
fi

# ── 4. Rotation des anciennes sauvegardes ───────────────────────

log "Nettoyage backups > ${RETENTION_DAYS} jours..."
DELETED_COUNT=$(find "$BACKUP_DIR" -name "edugest_${DB_NAME}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete -print | wc -l)
log "Supprimé: ${DELETED_COUNT} ancien(s) backup(s)"

# ── 5. Résumé ──────────────────────────────────────────────────

TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "edugest_${DB_NAME}_*.sql.gz" | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)

log "=== Sauvegarde terminée ==="
log "Fichier: ${DUMP_FILE}"
log "Taille: ${DUMP_SIZE}"
log "Lignes SQL: ${SQL_LINES}"
log "Total backups: ${TOTAL_BACKUPS}"
log "Espace total: ${TOTAL_SIZE}"
