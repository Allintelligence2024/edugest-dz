# Backup PostgreSQL Setup — EduGest DZ

## Vue d'ensemble

Script de sauvegarde automatique de la base PostgreSQL EduGest DZ avec :
- Compression gzip (niveau 9)
- Vérification d'intégrité
- Rotation automatique (30 jours par défaut)
- Logs structurés

---

## 1. Installation

### 1.1 Copier le script

```bash
cp scripts/backup-postgres.sh /usr/local/bin/backup-edugest.sh
chmod +x /usr/local/bin/backup-edugest.sh
```

### 1.2 Créer le répertoire de backups

```bash
sudo mkdir -p /var/backups/edugest
sudo chown postgres:postgres /var/backups/edugest
```

### 1.3 Configurer les variables

Éditer `/etc/edugest-backup.env` :

```bash
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=votre_mot_de_passe_securise
BACKUP_DIR=/var/backups/edugest
RETENTION_DAYS=30
```

> ⚠️ Protéger ce fichier : `chmod 600 /etc/edugest-backup.env`

---

## 2. Configuration Cron (Algérie VPS)

### 2.1 Sauvegarde quotidienne à 03h00 (heure d'Alger)

```bash
sudo crontab -e
```

Ajouter :

```cron
# ── Sauvegarde quotidienne EduGest DZ ──
# Tous les jours à 03h00 (UTC+1)
0 3 * * * /usr/bin/env $(cat /etc/edugest-backup.env) /usr/local/bin/backup-edugest.sh >> /var/log/edugest-backup.log 2>&1

# ── Sauvegarde hebdomadaire (dimanche) ──
# Dimanche à 02h00 — backup complet
0 2 * * 0 /usr/bin/env $(cat /etc/edugest-backup.env) /usr/local/bin/backup-edugest.sh >> /var/log/edugest-backup.log 2>&1
```

### 2.2 Vérifier le cron

```bash
sudo crontab -l
```

---

## 3. Restauration

### 3.1 Restauration complète

```bash
# Arrêter l'application
sudo systemctl stop php8.2-fpm

# Restaurer la base
gunzip -c /var/backups/edugest/edugest_edugestdz_20260716_030000.sql.gz | \
    PGPASSWORD=mot_de_passe psql -h localhost -U edugest_user -d edugestdz

# Redémarrer l'application
sudo systemctl start php8.2-fpm
```

### 3.2 Restauration partielle (une table)

```bash
# Extraire une table spécifique
gunzip -c /var/backups/edugest/edugest_edugestdz_*.sql.gz | \
    sed -n '/^-- Name: TABLE public.factures/,/^-- Name: /p' | \
    PGPASSWORD=mot_de_passe psql -h localhost -U edugest_user -d edugestdz
```

### 3.3 Vérification post-restauration

```bash
# Vérifier le nombre de lignes
psql -h localhost -U edugest_user -d edugestdz -c "
SELECT schemaname, relname, n_live_tup
FROM pg_stat_user_tables
ORDER BY n_live_tup DESC
LIMIT 20;
"
```

---

## 4. Monitoring

### 4.1 Vérifier les logs

```bash
tail -f /var/log/edugest-backup.log
```

### 4.2 Vérifier les backups existants

```bash
ls -lh /var/backups/edugest/edugest_edugestdz_*.sql.gz | tail -10
```

### 4.3 Alerte si pas de backup récent

```bash
# Script de vérification (ajouter au cron)
find /var/backups/edugest -name "*.sql.gz" -mtime -2 | wc -l
```

---

## 5. Sauvegarde distante (optionnel)

### 5.1 Rsync vers un serveur distant

```cron
# Sync quotidienne vers serveur backup
30 4 * * * rsync -avz /var/backups/edugest/ backup@serveur-distant:/backups/edugest/
```

### 5.2 S3-compatible (MinIO, AWS)

```bash
# Installer aws-cli
sudo apt install awscli

# Configurer
aws configure

# Upload
aws s3 sync /var/backups/edugest/ s3://bucket-edugest/backups/ --storage-class STANDARD_IA
```

---

## 6. Planification recommandée

| Fréquence | Type | Rétention | Priorité |
|---|---|---|---|
| Quotidienne 03h00 | Incremental | 30 jours | Haute |
| Hebdo dimanche 02h00 | Full | 90 jours | Moyenne |
| Mensuelle 1er du mois 01h00 | Full + S3 | 1 an | Basse |

---

*Dernière mise à jour : 2026-07-16*
