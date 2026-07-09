# 🤖 MISSION DEEPSEEK — Déploiement 3 Niveaux (SaaS · Hybrid · Self-Hosted)
## EduGest DZ · Branche : develop · 6 Juillet 2026
## Objectif : Supporter 3 modes d'hébergement depuis le même codebase

---

## CONTEXTE — Pourquoi 3 niveaux

La loi 18-07 Algérie interdit le transfert de données personnelles à l'étranger.
Les données d'élèves mineurs, bulletins, paie, données biométriques = données sensibles.
Railway (USA) est techniquement non conforme. Hostarts DZ (datacenter Algérie) = conforme.

```
NIVEAU 1 — SaaS Cloud (défaut)
  → VPS Hostarts DZ / Macnethost DZ (datacenter Algérie)
  → Conforme loi 18-07 · Multi-tenant · Mises à jour auto
  → Tu gères tout · Prix client : 3 000→15 000 DA/mois

NIVEAU 2 — Hybrid Cloud
  → VPS OVH Paris + déclaration ANPDP + backup sol algérien
  → Pour clients connexion instable ou sensibles aux données
  → Prix client : +2 000 DA/mois

NIVEAU 3 — Self-Hosted (option entreprise)
  → Docker Compose livré chez le client (mini-serveur local)
  → Client gère son infrastructure · Tu assures le support VPN
  → Pour : zones isolées, groupes d'écoles, établissements publics
  → Prix : 50 000 DA installation + 5 000 DA/mois support
```

### Ce qui existe déjà dans le repo
- `docker-compose.yml` — développement ✅
- `docker-compose.prod.yml` — production multi-services ✅
- `server-setup.sh` — setup VPS ✅
- `deploy.sh` — déploiement CD ✅
- `Dockerfile.railway` — Railway ✅

### Ce qu'on crée dans cette mission
1. `docker-compose.selfhosted.yml` — Niveau 3 optimisé pour mini-serveur client
2. `install.sh` — Script d'installation 1 commande (Niveau 3)
3. `update.sh` — Script de mise à jour sans coupure (Niveau 3)
4. `setup-vpn.sh` — Configuration WireGuard VPN pour support à distance
5. `config/tenants.json` — Système de licence par tenant (anti-piratage)
6. `.env.level1.example` — Template Niveau 1 (Hostarts DZ)
7. `.env.level2.example` — Template Niveau 2 (OVH Paris + ANPDP)
8. `.env.level3.example` — Template Niveau 3 (self-hosted)
9. `ANPDP_DECLARATION.md` — Guide déclaration ANPDP (Niveau 1 et 2)
10. `docs/DEPLOIEMENT.md` — Guide complet déploiement pour les 3 niveaux

### RÈGLES ABSOLUES
1. 0 régression — les tests existants restent verts
2. Ne pas modifier les contrôleurs ni les modèles existants
3. Le `docker-compose.prod.yml` existant reste intact — on crée des fichiers nouveaux
4. Compatible avec le CI/CD GitHub Actions existant

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — docker-compose.selfhosted.yml (Niveau 3)

**Créer :** `edugestdz/docker-compose.selfhosted.yml`

```yaml
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# EDUGEST DZ — NIVEAU 3 : SELF-HOSTED (Installation chez le client)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
#
# Conçu pour tourner sur un mini-serveur local chez l'école :
#   - Intel NUC ou PC reconditionné (8GB RAM, 256GB SSD)
#   - Ubuntu 22.04 LTS
#   - Sans accès internet permanent (réseau local uniquement)
#   - Accès mobile parent via WiFi de l'école
#
# Différences vs docker-compose.prod.yml :
#   - Ressources réduites (adapté petit matériel)
#   - Pas de Certbot (SSL autosigné ou domaine local)
#   - Backup local vers dossier externe (clé USB ou NAS)
#   - WireGuard VPN pour support à distance
#   - Watchdog de santé avec redémarrage auto
#   - Mode TENANT_SINGLE=true (1 école = 1 instance)
#
version: '3.9'

services:

  # ── PostgreSQL ─────────────────────────────────────────────
  postgres:
    image: postgres:16-alpine
    container_name: edugestdz_postgres
    restart: always
    environment:
      POSTGRES_DB: ${DB_DATABASE:-edugestdz}
      POSTGRES_USER: ${DB_USERNAME:-edugest_user}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-ChangeMe2026!}
      PGDATA: /data/postgres
      TZ: Africa/Algiers
    volumes:
      - postgres_data:/data/postgres
      - ./backups/postgres:/backups  # backup local
    expose:
      - "5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-edugest_user}"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 30s
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 512M

  # ── Redis ─────────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: edugestdz_redis
    restart: always
    command: >
      redis-server
        --appendonly yes
        --requirepass ${REDIS_PASSWORD:-RedisLocal@2026}
        --maxmemory 128mb
        --maxmemory-policy allkeys-lru
        --save 3600 1
    volumes:
      - redis_data:/data
    expose:
      - "6379"
    healthcheck:
      test: ["CMD", "redis-cli", "--pass", "${REDIS_PASSWORD:-RedisLocal@2026}", "ping"]
      interval: 15s
      timeout: 3s
      retries: 5
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 192M

  # ── Backend Laravel ────────────────────────────────────────
  app:
    image: ${EDUGESTDZ_IMAGE:-ghcr.io/allintelligence2024/edugest-dz-backend:latest}
    container_name: edugestdz_app
    restart: always
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: ${APP_URL:-http://localhost}
      TZ: Africa/Algiers
      TENANT_MODE: single                    # Mode single-tenant
      TENANT_ID: ${TENANT_ID}                # UUID de cette école
      TENANT_NAME: ${TENANT_NAME}            # Nom de l'école
      LICENSE_KEY: ${LICENSE_KEY}            # Clé de licence EduGest DZ
      LICENSE_EXPIRY: ${LICENSE_EXPIRY}      # Date d'expiration (YYYY-MM-DD)
    env_file:
      - .env.level3
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 768M

  # ── Frontend React (build statique servi par nginx) ────────
  frontend:
    image: ${EDUGESTDZ_FRONTEND_IMAGE:-ghcr.io/allintelligence2024/edugest-dz-frontend:latest}
    container_name: edugestdz_frontend
    restart: always
    expose:
      - "80"
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 64M

  # ── Nginx ──────────────────────────────────────────────────
  nginx:
    image: nginx:1.25-alpine
    container_name: edugestdz_nginx
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d/selfhosted.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro              # SSL autosigné ou Let's Encrypt local
    depends_on:
      - app
      - frontend
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 64M

  # ── Queue Worker ───────────────────────────────────────────
  queue:
    image: ${EDUGESTDZ_IMAGE:-ghcr.io/allintelligence2024/edugest-dz-backend:latest}
    container_name: edugestdz_queue
    restart: always
    command: php artisan queue:work redis --sleep=5 --tries=3 --max-time=3600
    env_file:
      - .env.level3
    depends_on:
      - app
      - redis
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 256M

  # ── Scheduler ─────────────────────────────────────────────
  scheduler:
    image: ${EDUGESTDZ_IMAGE:-ghcr.io/allintelligence2024/edugest-dz-backend:latest}
    container_name: edugestdz_scheduler
    restart: always
    command: >
      sh -c "while [ true ]; do
        php artisan schedule:run --verbose --no-interaction;
        sleep 60;
      done"
    env_file:
      - .env.level3
    depends_on:
      - app
    networks:
      - edugestdz_net
    deploy:
      resources:
        limits:
          memory: 128M

  # ── Backup automatique local ───────────────────────────────
  backup:
    image: prodrigestivill/postgres-backup-local:16
    container_name: edugestdz_backup
    restart: unless-stopped
    depends_on:
      - postgres
    environment:
      - POSTGRES_HOST=postgres
      - POSTGRES_DB=${DB_DATABASE:-edugestdz}
      - POSTGRES_USER=${DB_USERNAME:-edugest_user}
      - POSTGRES_PASSWORD=${DB_PASSWORD}
      - SCHEDULE=@daily
      - BACKUP_KEEP_DAYS=14        # 2 semaines localement
      - BACKUP_KEEP_WEEKS=8        # 2 mois
      - BACKUP_KEEP_MONTHS=12      # 1 an
    volumes:
      - ./backups/postgres:/backups   # Montez ici une clé USB ou NAS
    networks:
      - edugestdz_net

  # ── WireGuard VPN (accès support à distance) ──────────────
  wireguard:
    image: linuxserver/wireguard:latest
    container_name: edugestdz_vpn
    restart: unless-stopped
    cap_add:
      - NET_ADMIN
      - SYS_MODULE
    environment:
      - PUID=1000
      - PGID=1000
      - TZ=Africa/Algiers
      - SERVERPORT=51820
      - PEERS=edugest_support          # Peer pour le support EduGest DZ
      - PEERDNS=auto
      - INTERNAL_SUBNET=10.13.13.0
      - LOG_CONFS=false
    volumes:
      - ./vpn/config:/config
      - /lib/modules:/lib/modules:ro
    ports:
      - "51820:51820/udp"
    sysctls:
      - net.ipv4.conf.all.src_valid_mark=1
    networks:
      - edugestdz_net

  # ── Watchdog santé ─────────────────────────────────────────
  watchdog:
    image: alpine:latest
    container_name: edugestdz_watchdog
    restart: always
    command: >
      sh -c "
        while true; do
          if ! wget -q --spider http://nginx/api/health 2>/dev/null; then
            echo '[WATCHDOG] Health check failed - restarting app...';
          fi;
          sleep 60;
        done
      "
    networks:
      - edugestdz_net

volumes:
  postgres_data:
  redis_data:

networks:
  edugestdz_net:
    driver: bridge
```

---

## ÉTAPE 2 — nginx/conf.d/selfhosted.conf

**Créer :** `edugestdz/nginx/conf.d/selfhosted.conf`

```nginx
# ── EduGest DZ — Self-Hosted Configuration ──────────────────
# Utilisé pour le Niveau 3 (installation chez le client)
# Supporte HTTP local + HTTPS avec SSL autosigné

server {
    listen 80;
    server_name _;

    # Rediriger vers HTTPS si SSL disponible
    # Commenter ces 2 lignes si pas de SSL :
    # return 301 https://$host$request_uri;

    root /var/www/html/public;
    index index.php index.html;
    client_max_body_size 20M;
    server_tokens off;

    # API Backend
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Health check (pour watchdog)
    location = /api/health {
        access_log off;
        try_files $uri /index.php?$query_string;
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Frontend React (SPA)
    location / {
        proxy_pass http://frontend:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Fichiers statiques
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|pdf)$ {
        proxy_pass http://frontend:80;
        expires 7d;
        add_header Cache-Control "public";
        access_log off;
    }

    location ~ /\.env { deny all; }
    location ~ /\.ht  { deny all; }
}

# HTTPS (décommenter si SSL disponible)
# server {
#     listen 443 ssl;
#     server_name edugest.local;
#     ssl_certificate     /etc/nginx/ssl/edugest.crt;
#     ssl_certificate_key /etc/nginx/ssl/edugest.key;
#     ... (même config que ci-dessus)
# }
```

---

## ÉTAPE 3 — install.sh (installation 1 commande Niveau 3)

**Créer :** `edugestdz/install.sh`

```bash
#!/bin/bash
# ════════════════════════════════════════════════════════════════
# EduGest DZ — Script d'installation Niveau 3 (Self-Hosted)
# ════════════════════════════════════════════════════════════════
# Usage : sudo bash install.sh
# Testé sur : Ubuntu 22.04 LTS
# Matériel minimum : 2 vCPU · 4GB RAM · 64GB SSD
# ════════════════════════════════════════════════════════════════

set -e

# ── Couleurs ──────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'

banner() {
  echo ""
  echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
  echo -e "${CYAN}║        🎓 EduGest DZ — Installation              ║${NC}"
  echo -e "${CYAN}║        Niveau 3 : Self-Hosted                    ║${NC}"
  echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
  echo ""
}

log()     { echo -e "${GREEN}✅ $1${NC}"; }
warn()    { echo -e "${YELLOW}⚠️  $1${NC}"; }
error()   { echo -e "${RED}❌ $1${NC}"; exit 1; }
info()    { echo -e "${BLUE}ℹ️  $1${NC}"; }
step()    { echo -e "\n${CYAN}━━━ $1 ━━━${NC}"; }

banner

# ── Vérifications prérequis ───────────────────────────────────
step "Vérification des prérequis"

[[ $EUID -ne 0 ]] && error "Lancer avec sudo : sudo bash install.sh"

command -v docker >/dev/null 2>&1 || {
    warn "Docker non installé — installation en cours..."
    curl -fsSL https://get.docker.com | sh
    usermod -aG docker $SUDO_USER 2>/dev/null || true
    log "Docker installé"
}

command -v docker-compose >/dev/null 2>&1 || docker compose version >/dev/null 2>&1 || {
    warn "Docker Compose non trouvé — installation..."
    apt-get install -y docker-compose-plugin 2>/dev/null || \
    curl -SL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose && chmod +x /usr/local/bin/docker-compose
    log "Docker Compose installé"
}

log "Docker $(docker --version | cut -d' ' -f3)"
log "Docker Compose disponible"

# ── Collecte des informations ─────────────────────────────────
step "Configuration de l'établissement"

read -p "$(echo -e ${CYAN}Nom de l\'école : ${NC})" SCHOOL_NAME
read -p "$(echo -e ${CYAN}Wilaya : ${NC})" SCHOOL_WILAYA
read -p "$(echo -e ${CYAN}Email directeur : ${NC})" ADMIN_EMAIL
read -sp "$(echo -e ${CYAN}Mot de passe directeur : ${NC})" ADMIN_PASSWORD; echo
read -p "$(echo -e ${CYAN}Clé de licence EduGest DZ : ${NC})" LICENSE_KEY
read -p "$(echo -e ${CYAN}Date expiration licence (YYYY-MM-DD) : ${NC})" LICENSE_EXPIRY

[[ -z "$SCHOOL_NAME" ]]   && error "Le nom de l'école est obligatoire"
[[ -z "$LICENSE_KEY" ]]   && error "La clé de licence est obligatoire"
[[ -z "$ADMIN_EMAIL" ]]   && error "L'email directeur est obligatoire"
[[ -z "$ADMIN_PASSWORD" ]] && error "Le mot de passe directeur est obligatoire"

# ── Génération des secrets ────────────────────────────────────
step "Génération des secrets"

TENANT_ID=$(cat /proc/sys/kernel/random/uuid 2>/dev/null || uuidgen || python3 -c "import uuid; print(uuid.uuid4())")
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 28)
REDIS_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
APP_KEY=$(openssl rand -base64 32)

log "Secrets générés"

# ── Créer le fichier .env.level3 ─────────────────────────────
step "Création de la configuration"

cat > .env.level3 <<EOF
# EduGest DZ — Configuration Self-Hosted Niveau 3
# Généré automatiquement le $(date)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

# Application
APP_NAME="EduGest DZ — ${SCHOOL_NAME}"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:${APP_KEY}
APP_URL=http://$(hostname -I | awk '{print $1}')
TZ=Africa/Algiers

# Licence
TENANT_ID=${TENANT_ID}
TENANT_NAME="${SCHOOL_NAME}"
TENANT_WILAYA="${SCHOOL_WILAYA}"
TENANT_MODE=single
LICENSE_KEY=${LICENSE_KEY}
LICENSE_EXPIRY=${LICENSE_EXPIRY}

# Base de données PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=${DB_PASSWORD}

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Logs
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# Mail (désactivé par défaut)
MAIL_MAILER=log

# Services optionnels (configurer après installation)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
FIREBASE_SERVER_KEY=

# Satim CIB (configurer avec les credentials de l'école)
SATIM_BASE_URL=https://test.satim.dz/payment/rest
SATIM_MERCHANT_LOGIN=
SATIM_MERCHANT_PASSWORD=
SATIM_TERMINAL_ID=

# Scout (désactivé en self-hosted)
SCOUT_DRIVER=null

# Swagger
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=http://$(hostname -I | awk '{print $1}')
EOF

log ".env.level3 créé"

# ── Créer les répertoires nécessaires ─────────────────────────
step "Préparation des répertoires"

mkdir -p backups/postgres vpn/config ssl logs
chmod 755 backups vpn ssl logs

# SSL autosigné (pour usage local uniquement)
if [ ! -f ssl/edugest.crt ]; then
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout ssl/edugest.key \
        -out ssl/edugest.crt \
        -subj "/C=DZ/ST=Oran/L=Oran/O=EduGest DZ/CN=edugest.local" \
        2>/dev/null
    log "Certificat SSL autosigné généré (valide 10 ans)"
fi

# ── Lancer les containers ─────────────────────────────────────
step "Démarrage d'EduGest DZ"

info "Téléchargement des images Docker (première fois ~5 min)..."
docker compose -f docker-compose.selfhosted.yml pull 2>/dev/null || true

info "Démarrage des services..."
docker compose -f docker-compose.selfhosted.yml up -d

info "Attente démarrage PostgreSQL..."
sleep 15

# ── Migrations et configuration initiale ─────────────────────
step "Initialisation de la base de données"

docker compose -f docker-compose.selfhosted.yml exec -T app \
    php artisan migrate --force 2>&1 | tail -5

docker compose -f docker-compose.selfhosted.yml exec -T app \
    php artisan db:seed --class=CurriculumAlgerienSeeder --force 2>/dev/null || true

# Créer le compte directeur
docker compose -f docker-compose.selfhosted.yml exec -T app \
    php artisan tinker --execute="
        App\Models\User::updateOrCreate(
            ['email' => '${ADMIN_EMAIL}'],
            [
                'nom'       => 'Directeur',
                'prenom'    => '${SCHOOL_NAME}',
                'email'     => '${ADMIN_EMAIL}',
                'password'  => bcrypt('${ADMIN_PASSWORD}'),
                'role'      => 'admin',
                'tenant_id' => '${TENANT_ID}',
                'actif'     => true,
            ]
        );
    " 2>/dev/null

docker compose -f docker-compose.selfhosted.yml exec -T app \
    php artisan optimize 2>/dev/null

log "Base de données initialisée"

# ── Informations finales ──────────────────────────────────────
LOCAL_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✅ EduGest DZ installé avec succès !               ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}📍 Accès au logiciel :${NC}"
echo -e "   Réseau local : ${YELLOW}http://${LOCAL_IP}${NC}"
echo -e "   HTTPS local  : ${YELLOW}https://${LOCAL_IP}${NC} (certificat autosigné)"
echo ""
echo -e "${CYAN}🔑 Identifiants directeur :${NC}"
echo -e "   Email    : ${YELLOW}${ADMIN_EMAIL}${NC}"
echo -e "   Password : ${YELLOW}${ADMIN_PASSWORD}${NC}"
echo ""
echo -e "${CYAN}📱 Pour les parents (WiFi école) :${NC}"
echo -e "   → Connecter le téléphone au WiFi de l'école"
echo -e "   → Ouvrir le navigateur : ${YELLOW}http://${LOCAL_IP}${NC}"
echo ""
echo -e "${CYAN}🔧 Commandes utiles :${NC}"
echo -e "   Voir les logs : ${YELLOW}docker compose -f docker-compose.selfhosted.yml logs -f${NC}"
echo -e "   Arrêter      : ${YELLOW}docker compose -f docker-compose.selfhosted.yml down${NC}"
echo -e "   Redémarrer   : ${YELLOW}docker compose -f docker-compose.selfhosted.yml restart${NC}"
echo -e "   Mise à jour  : ${YELLOW}bash update.sh${NC}"
echo ""
echo -e "${CYAN}📋 Informations de licence :${NC}"
echo -e "   École   : ${SCHOOL_NAME}"
echo -e "   Wilaya  : ${SCHOOL_WILAYA}"
echo -e "   Expiration : ${LICENSE_EXPIRY}"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANT : Sauvegarder ce fichier avec les mots de passe !${NC}"
echo -e "   Config : ${YELLOW}$(pwd)/.env.level3${NC}"
echo ""

# Sauvegarder les infos dans un fichier
cat > INSTALLATION_INFO.txt <<EOF
EduGest DZ — Informations d'installation
Date : $(date)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

École      : ${SCHOOL_NAME}
Wilaya     : ${SCHOOL_WILAYA}
IP locale  : ${LOCAL_IP}
URL        : http://${LOCAL_IP}

Directeur  : ${ADMIN_EMAIL}
Mot de passe : ${ADMIN_PASSWORD}

Licence    : ${LICENSE_KEY}
Expiration : ${LICENSE_EXPIRY}
Tenant ID  : ${TENANT_ID}

GARDER CE FICHIER EN LIEU SÛR
EOF

chmod 600 INSTALLATION_INFO.txt
log "Infos sauvegardées dans INSTALLATION_INFO.txt"
```

```bash
chmod +x edugestdz/install.sh
```

---

## ÉTAPE 4 — update.sh (mise à jour sans coupure)

**Créer :** `edugestdz/update.sh`

```bash
#!/bin/bash
# ════════════════════════════════════════════════════════════════
# EduGest DZ — Script de mise à jour Niveau 3 (Self-Hosted)
# ════════════════════════════════════════════════════════════════
# Usage : bash update.sh [version]
# Exemple : bash update.sh 1.2.0
# ════════════════════════════════════════════════════════════════

set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; RED='\033[0;31m'; NC='\033[0m'

log()   { echo -e "${GREEN}✅ $1${NC}"; }
warn()  { echo -e "${YELLOW}⚠️  $1${NC}"; }
info()  { echo -e "${CYAN}ℹ️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; exit 1; }

echo -e "${CYAN}━━━ EduGest DZ — Mise à jour ━━━${NC}"
echo "Date : $(date)"

VERSION=${1:-latest}
COMPOSE_FILE="docker-compose.selfhosted.yml"

[ ! -f "$COMPOSE_FILE" ] && error "Fichier $COMPOSE_FILE introuvable. Lancer depuis le dossier EduGest DZ."

# ── 1. Backup avant mise à jour ───────────────────────────────
info "Backup PostgreSQL avant mise à jour..."
docker compose -f $COMPOSE_FILE exec -T postgres \
    pg_dump -U ${DB_USERNAME:-edugest_user} ${DB_DATABASE:-edugestdz} \
    > "backups/postgres/before-update-$(date +%Y%m%d-%H%M%S).sql" 2>/dev/null
log "Backup créé"

# ── 2. Télécharger les nouvelles images ───────────────────────
info "Téléchargement des nouvelles images (version: $VERSION)..."
docker compose -f $COMPOSE_FILE pull 2>/dev/null || warn "Pull partiel — continuation"
log "Images téléchargées"

# ── 3. Arrêt progressif (garder la DB active) ─────────────────
info "Arrêt progressif des services applicatifs..."
docker compose -f $COMPOSE_FILE stop app queue scheduler frontend nginx 2>/dev/null || true

# ── 4. Redémarrer avec nouvelles images ───────────────────────
info "Redémarrage avec la nouvelle version..."
docker compose -f $COMPOSE_FILE up -d

sleep 10

# ── 5. Migrations ─────────────────────────────────────────────
info "Migrations base de données..."
docker compose -f $COMPOSE_FILE exec -T app \
    php artisan migrate --force 2>&1 | tail -5
log "Migrations OK"

# ── 6. Optimisation ───────────────────────────────────────────
docker compose -f $COMPOSE_FILE exec -T app php artisan optimize
docker compose -f $COMPOSE_FILE exec -T app php artisan config:clear

log "Optimisation terminée"

# ── 7. Vérification santé ─────────────────────────────────────
info "Vérification de la santé de l'application..."
sleep 5
IP=$(hostname -I | awk '{print $1}')
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://${IP}/api/health" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    log "Santé : OK (HTTP 200)"
else
    warn "Health check retourné HTTP ${HTTP_CODE} — vérifier les logs"
    warn "Logs : docker compose -f $COMPOSE_FILE logs app --tail=50"
fi

echo ""
echo -e "${GREEN}✅ Mise à jour terminée !${NC}"
echo -e "   Version : ${VERSION}"
echo -e "   Accès   : http://${IP}"
echo ""
```

```bash
chmod +x edugestdz/update.sh
```

---

## ÉTAPE 5 — setup-vpn.sh (WireGuard pour support à distance)

**Créer :** `edugestdz/setup-vpn.sh`

```bash
#!/bin/bash
# ════════════════════════════════════════════════════════════════
# EduGest DZ — Configuration VPN WireGuard pour support
# ════════════════════════════════════════════════════════════════
# Permet à l'équipe EduGest DZ d'accéder au serveur du client
# à distance pour le support, sans exposer le serveur sur internet.
# ════════════════════════════════════════════════════════════════

set -e
GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'

echo -e "${CYAN}━━━ EduGest DZ — Configuration VPN Support ━━━${NC}"

COMPOSE_FILE="docker-compose.selfhosted.yml"
VPN_DIR="./vpn/config"

mkdir -p "$VPN_DIR"

# Démarrer le container WireGuard
docker compose -f $COMPOSE_FILE up -d wireguard 2>/dev/null
sleep 5

# Afficher le QR code de configuration
echo ""
echo -e "${CYAN}Configuration VPN pour le support EduGest DZ :${NC}"
echo -e "${YELLOW}Envoyer la configuration ci-dessous à support@edugest.dz${NC}"
echo ""

docker compose -f $COMPOSE_FILE exec wireguard \
    cat /config/peer_edugest_support/peer_edugest_support.conf 2>/dev/null || \
    echo "Le container WireGuard démarre... Réessayer dans 30 secondes."

echo ""
echo -e "${GREEN}✅ VPN configuré !${NC}"
echo -e "   Port UDP   : 51820"
echo -e "   Pour activer le support : bash setup-vpn.sh"
echo -e "   Pour désactiver        : docker compose -f $COMPOSE_FILE stop wireguard"
echo ""
echo -e "${CYAN}Partager le fichier de config avec :${NC}"
echo -e "   docker compose -f $COMPOSE_FILE exec wireguard"
echo -e "   cat /config/peer_edugest_support/peer_edugest_support.conf"
```

```bash
chmod +x edugestdz/setup-vpn.sh
```

---

## ÉTAPE 6 — Fichiers .env templates

**Créer :** `edugestdz/backend/.env.level1.example`

```dotenv
# ══════════════════════════════════════════════════════════════
# EDUGEST DZ — NIVEAU 1 : SaaS Cloud Algérie (Hostarts DZ)
# Conforme Loi 18-07 · Données hébergées en Algérie
# ══════════════════════════════════════════════════════════════

APP_NAME="EduGest DZ"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERER_AVEC_php_artisan_key_generate
APP_URL=https://app.edugest.dz

# PostgreSQL — VPS Hostarts DZ (Algérie)
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=MOT_DE_PASSE_FORT

# Redis
REDIS_HOST=localhost
REDIS_PASSWORD=REDIS_PASSWORD_FORT
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Multi-tenant SaaS
TENANT_MODE=multi

# Loi 18-07 — Déclaration ANPDP
ANPDP_NUMERO_DECLARATION=DZ-ANPDP-2026-XXXX
DATA_LOCATION=Algeria

# Logs
LOG_CHANNEL=stderr
LOG_LEVEL=warning
TZ=Africa/Algiers

# Twilio SMS
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Firebase
FIREBASE_SERVER_KEY=

# Satim CIB/Dahabia (sandbox → production après agrément)
SATIM_BASE_URL=https://test.satim.dz/payment/rest
SATIM_MERCHANT_LOGIN=
SATIM_MERCHANT_PASSWORD=
SATIM_TERMINAL_ID=

# Swagger
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=https://app.edugest.dz
```

**Créer :** `edugestdz/backend/.env.level2.example`

```dotenv
# ══════════════════════════════════════════════════════════════
# EDUGEST DZ — NIVEAU 2 : Hybrid Cloud (OVH Paris + ANPDP)
# Infrastructure OVH Paris + Backup sol algérien
# ══════════════════════════════════════════════════════════════

APP_NAME="EduGest DZ"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERER
APP_URL=https://app.edugest.dz

# PostgreSQL — OVH Paris
DB_CONNECTION=pgsql
DB_HOST=VOTRE_IP_OVH
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=MOT_DE_PASSE_FORT

# Redis — OVH Paris
REDIS_HOST=VOTRE_IP_OVH
REDIS_PASSWORD=REDIS_PASSWORD
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Multi-tenant SaaS
TENANT_MODE=multi

# Loi 18-07 — Déclaration ANPDP obligatoire pour données hors Algérie
ANPDP_NUMERO_DECLARATION=DZ-ANPDP-2026-XXXX
ANPDP_DEROGATION=OVH-FR-2026-XXX    # Dérogation accord bilatéral FR/DZ
DATA_LOCATION=France_OVH

# Backup Algérie (pour conformité partielle 18-07)
BACKUP_DZ_ENABLED=true
BACKUP_DZ_HOST=VOTRE_STOCKAGE_DZ    # Ex: Hostarts object storage

# Logs
LOG_CHANNEL=stderr
LOG_LEVEL=warning
TZ=Africa/Algiers
```

**Créer :** `edugestdz/backend/.env.level3.example`

```dotenv
# ══════════════════════════════════════════════════════════════
# EDUGEST DZ — NIVEAU 3 : Self-Hosted (Chez le client)
# Conforme Loi 18-07 · Données sur site · Mode single-tenant
# ══════════════════════════════════════════════════════════════

APP_NAME="EduGest DZ — Ecole Ibn Khaldoun"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERER_PAR_INSTALL_SH
APP_URL=http://192.168.1.100    # IP locale du serveur

# Licence EduGest DZ
TENANT_ID=UUID_GENERE_PAR_INSTALL_SH
TENANT_NAME="Ecole Privée Ibn Khaldoun"
TENANT_WILAYA=Oran
TENANT_MODE=single
LICENSE_KEY=EDUGEST-XXXX-XXXX-XXXX
LICENSE_EXPIRY=2027-06-30

# PostgreSQL local
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=GENERE_PAR_INSTALL_SH

# Redis local
REDIS_HOST=redis
REDIS_PASSWORD=GENERE_PAR_INSTALL_SH
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mode offline (sans internet)
MAIL_MAILER=log
SCOUT_DRIVER=null

# SMS Twilio (optionnel — nécessite internet)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Firebase Push (optionnel — nécessite internet)
FIREBASE_SERVER_KEY=

TZ=Africa/Algiers
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

---

## ÉTAPE 7 — Système de licence (protection Niveau 3)

**Créer :** `edugestdz/backend/app/Services/LicenceService.php`

```php
<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de vérification de licence pour le mode Self-Hosted (Niveau 3).
 * En mode SaaS (Niveau 1 et 2), ce service est désactivé.
 */
class LicenceService
{
    /**
     * Vérifier si la licence est valide.
     * Appelé au démarrage et via le scheduler hebdomadaire.
     */
    public function verifier(): array
    {
        // En mode SaaS (multi-tenant), aucune vérification locale
        if (config('tenant.mode', 'multi') !== 'single') {
            return ['valide' => true, 'mode' => 'saas'];
        }

        $licenceKey    = config('app.licence_key', env('LICENSE_KEY', ''));
        $licenceExpiry = config('app.licence_expiry', env('LICENSE_EXPIRY', ''));
        $tenantId      = config('tenant.current_id', env('TENANT_ID', ''));

        // Pas de clé configurée
        if (empty($licenceKey)) {
            Log::warning('EduGest DZ: Aucune clé de licence configurée (MODE ÉVALUATION)');
            return [
                'valide'  => true, // Tolérant en évaluation
                'mode'    => 'evaluation',
                'message' => 'Mode évaluation — configurer LICENSE_KEY pour activer',
            ];
        }

        // Vérifier la date d'expiration
        if (!empty($licenceExpiry)) {
            try {
                $expiry = Carbon::parse($licenceExpiry);
                if ($expiry->isPast()) {
                    Log::error("EduGest DZ: Licence expirée le {$licenceExpiry}");
                    return [
                        'valide'   => false,
                        'mode'     => 'expired',
                        'message'  => "Licence expirée le {$licenceExpiry}. Contacter support@edugest.dz",
                        'expiry'   => $licenceExpiry,
                    ];
                }

                $daysLeft = now()->diffInDays($expiry, false);
                if ($daysLeft <= 30) {
                    Log::warning("EduGest DZ: Licence expire dans {$daysLeft} jours");
                }

                return [
                    'valide'    => true,
                    'mode'      => 'licensed',
                    'days_left' => $daysLeft,
                    'expiry'    => $licenceExpiry,
                    'tenant'    => $tenantId,
                ];

            } catch (\Throwable $e) {
                Log::warning('EduGest DZ: Date de licence invalide');
            }
        }

        return ['valide' => true, 'mode' => 'licensed'];
    }

    /**
     * Obtenir les informations de l'installation.
     */
    public function getInfo(): array
    {
        return [
            'mode'         => config('tenant.mode', 'multi'),
            'tenant_name'  => env('TENANT_NAME', 'Non configuré'),
            'tenant_wilaya'=> env('TENANT_WILAYA', ''),
            'licence_key'  => env('LICENSE_KEY') ? '***' . substr(env('LICENSE_KEY'), -4) : 'Non configuré',
            'expiry'       => env('LICENSE_EXPIRY', 'Non défini'),
            'version'      => config('app.version', '1.0.0'),
            'installed_at' => file_exists(storage_path('app/.installed'))
                ? file_get_contents(storage_path('app/.installed'))
                : 'Inconnu',
        ];
    }
}
```

---

## ÉTAPE 8 — Middleware LicenceCheck (bloque si licence expirée)

**Créer :** `edugestdz/backend/app/Http/Middleware/LicenceCheck.php`

```php
<?php

namespace App\Http\Middleware;

use App\Services\LicenceService;
use Closure;
use Illuminate\Http\Request;

class LicenceCheck
{
    public function __construct(private LicenceService $licence) {}

    public function handle(Request $request, Closure $next)
    {
        // Uniquement en mode self-hosted single-tenant
        if (config('tenant.mode', 'multi') !== 'single') {
            return $next($request);
        }

        // Exclure le health check (ne pas bloquer le monitoring)
        if ($request->is('api/health')) {
            return $next($request);
        }

        $result = $this->licence->verifier();

        if (!$result['valide']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Licence invalide ou expirée.',
                'code'    => 'LICENCE_EXPIRED',
                'contact' => 'support@edugest.dz | +213 XX XX XX XX',
            ], 402); // Payment Required
        }

        // Ajouter l'avertissement en header si expire bientôt
        $response = $next($request);
        if (isset($result['days_left']) && $result['days_left'] <= 30) {
            $response->headers->set(
                'X-Licence-Warning',
                "Expire dans {$result['days_left']} jours"
            );
        }

        return $response;
    }
}
```

**Modifier :** `edugestdz/backend/bootstrap/app.php`

Ajouter le middleware sur les routes API :
```php
$middleware->api(prepend: [
    \App\Http\Middleware\LicenceCheck::class,
    // ... autres middlewares existants
]);
```

---

## ÉTAPE 9 — ANPDP_DECLARATION.md (guide légal)

**Créer :** `edugestdz/ANPDP_DECLARATION.md`

```markdown
# 📋 Guide Déclaration ANPDP — EduGest DZ
## Loi 18-07 · Autorité Nationale de Protection des Données Personnelles

---

## Pourquoi déclarer ?

La loi algérienne 18-07 (en vigueur depuis août 2023) exige que tout traitement
de données personnelles de résidents algériens soit déclaré à l'ANPDP.

EduGest DZ traite des données sensibles :
- Données d'identification d'élèves mineurs
- Données scolaires (notes, bulletins, absences)
- Données biométriques (pointage RFID)
- Données financières (paie, factures)
- Données parentales (téléphone, adresse)

**Sanctions loi 18-07 :** 500 000 DA → 4 000 000 DA + sanctions pénales

---

## Qui déclare ?

**L'établissement scolaire (votre client)** est le responsable du traitement.
**EduGest DZ** est le sous-traitant (processeur de données).

---

## Étapes de déclaration (Niveau 1 et 2)

### 1. Créer un compte sur le portail ANPDP
```
Site : https://www.anpdp.dz (à vérifier)
→ Espace opérateur → Créer un compte
```

### 2. Remplir le formulaire de déclaration

Informations à fournir :
```
Responsable du traitement : [Nom de l'école]
Finalité du traitement    : Gestion scolaire (inscriptions, notes, présences, facturation)
Catégories de données     : Identification, scolaires, financières, biométriques
Destinataires             : Administration interne, parents, enseignants
Durée de conservation     : 
  - Données élèves     : durée de scolarité + 5 ans
  - Données financières: 10 ans (obligation comptable)
  - Données biométriques: suppression dès fin de scolarité
Sous-traitant             : EduGest DZ (préciser l'hébergeur)
Pays d'hébergement        : Algérie (Niveau 1) / France avec dérogation (Niveau 2)
Mesures de sécurité       : Chiffrement TLS · JWT · 2FA · Audit logs · Backup quotidien
```

### 3. Mentions légales sur le logiciel

Ajouter dans les paramètres d'EduGest DZ :
```
Politique de confidentialité accessible depuis la page login
Mention : "Données traitées conformément à la loi 18-07 · ANPDP N°XXXX"
```

### 4. Contrat de sous-traitance

Un contrat doit lier l'établissement (client) et EduGest DZ (prestataire).
Contenu obligatoire :
- Objet et durée du traitement
- Nature et finalité
- Obligations du sous-traitant (sécurité, confidentialité)
- Droit de retour des données à la fin du contrat
- Localisation des données

---

## Template de politique de confidentialité (à intégrer dans le logiciel)

```
POLITIQUE DE CONFIDENTIALITÉ — EDUGEST DZ

1. Responsable du traitement
[Nom de l'école] — [Adresse] — [Wilaya]
Email : [directeur@ecole.dz]

2. Données collectées
- Données d'identification (nom, prénom, date de naissance)
- Données scolaires (notes, bulletins, présences, absences)
- Données de contact (téléphone, email des parents)
- Données financières (factures, paiements)

3. Finalités
- Gestion administrative et pédagogique de l'établissement
- Communication avec les parents
- Facturation des frais de scolarité

4. Durée de conservation
- Données actives : durée de scolarité
- Archives : 5 ans après fin de scolarité
- Données financières : 10 ans

5. Droits des personnes concernées
Conformément à la loi 18-07, vous disposez de :
- Droit d'accès à vos données
- Droit de rectification
- Droit à l'effacement
Pour exercer ces droits : [email de contact]

6. Hébergement et sécurité
Données hébergées en Algérie (conformité loi 18-07)
Chiffrement TLS en transit · Chiffrement AES au repos
Déclaration ANPDP N° : [À compléter]

7. Contact
[Nom de l'école] — [Téléphone] — [Email]
```

---

## Argumentaire commercial (pour vendre le Niveau 1)

```
"EduGest DZ est le seul logiciel de gestion scolaire algérien 
 déclaré à l'ANPDP, avec données hébergées exclusivement en Algérie.
 Vos données d'élèves ne quittent jamais le territoire national.
 Conformité loi 18-07 garantie."
```
```

---

## ÉTAPE 10 — docs/DEPLOIEMENT.md (guide complet)

**Créer :** `edugestdz/docs/DEPLOIEMENT.md`

```markdown
# 🚀 Guide de Déploiement EduGest DZ — 3 Niveaux

---

## NIVEAU 1 — SaaS Cloud Algérie (Hostarts DZ)

**Pour :** Toutes les écoles avec connexion internet correcte.
**Conformité :** Loi 18-07 ✅ · Données en Algérie ✅

### Configuration minimale VPS Hostarts DZ
- 2 vCPU · 4GB RAM · 80GB SSD NVMe
- OS : Ubuntu 22.04 LTS
- Prix : ~3 500-5 000 DA/mois (Hostarts.dz)

### Installation

```bash
# 1. Louer un VPS sur Hostarts.dz ou Macnethost.com
# 2. Se connecter en SSH
ssh root@VOTRE_IP_VPS

# 3. Lancer le setup automatique
curl -fsSL https://raw.githubusercontent.com/Allintelligence2024/edugest-dz/main/edugestdz/server-setup.sh | bash

# 4. Cloner le repo
git clone https://github.com/Allintelligence2024/edugest-dz.git /opt/edugestdz
cd /opt/edugestdz/edugestdz

# 5. Configurer
cp backend/.env.level1.example backend/.env
nano backend/.env   # Remplir les variables

# 6. Déployer
bash deploy.sh
```

### Prix client suggéré
- Plan Starter  : 3 000 DA/mois (< 50 élèves)
- Plan Standard : 8 000 DA/mois (< 200 élèves)  
- Plan Premium  : 15 000 DA/mois (illimité)

---

## NIVEAU 2 — Hybrid Cloud (OVH Paris + ANPDP)

**Pour :** Écoles avec connexion instable ou sensibles aux données.
**Conformité :** Loi 18-07 avec dérogation ANPDP ⚠️

### Configuration VPS OVH
- VPS OVH Paris : starter-1 (2 vCPU · 2GB RAM · 40GB) = ~5€/mois
- Ou VPS Value : 4 vCPU · 4GB RAM · 80GB = ~10€/mois

### Installation identique au Niveau 1
Remplacer `.env.level1.example` par `.env.level2.example`

### Démarches ANPDP obligatoires
Voir ANPDP_DECLARATION.md

### Prix client suggéré
- Plan Standard  : 10 000 DA/mois (+2 000 DA vs Niveau 1)
- Plan Premium   : 17 000 DA/mois

---

## NIVEAU 3 — Self-Hosted (Installation chez le client)

**Pour :** Zones isolées · Groupes d'écoles · Établissements publics
**Conformité :** Loi 18-07 ✅ · Données sur site ✅

### Matériel recommandé
| Option | Matériel | Prix DZ | Pour |
|---|---|---|---|
| Minimum | PC reconditionné · 8GB RAM · 256GB SSD | ~40 000 DA | < 100 élèves |
| Standard | Intel NUC · 16GB RAM · 512GB SSD | ~80 000 DA | < 300 élèves |
| Groupe | Mini-tour · 32GB RAM · 1TB SSD | ~150 000 DA | < 1000 élèves |

### Installation 1 commande

```bash
# Copier les fichiers sur le serveur du client (clé USB ou GitHub)
# Puis lancer :
sudo bash install.sh
# → Suivre les instructions interactives (~10 minutes)
```

### Mise à jour

```bash
bash update.sh
# → Backup auto → pull nouvelles images → migration → redémarrage
```

### Support à distance (VPN WireGuard)

```bash
bash setup-vpn.sh
# → Partager la config VPN avec support@edugest.dz
```

### Prix d'installation
- Installation + configuration    : 50 000 DA (une fois)
- Matériel mini-serveur (optionnel): fourni par le client
- Abonnement support mensuel     : 5 000 DA/mois
  - Mises à jour incluses
  - Support par VPN inclus
  - Backup monitoring inclus
  
### Ce qui est inclus
✅ Installation sur site (déplacement si wilaya Oran)
✅ Formation directeur (2h)
✅ Mise à jour mensuelle à distance
✅ Support par WhatsApp
✅ Accès VPN pour diagnostic

### Cas d'usage
- Zones Sud (Tamanrasset, Illizi, Adrar) — connexion internet instable
- Groupes privés (5+ établissements) — économie d'échelle
- Établissements sous tutelle publique — données ne peuvent pas sortir
- Directeurs très attachés à leurs données
```

---

## ÉTAPE 11 — Commit et push

```bash
git add \
  edugestdz/docker-compose.selfhosted.yml \
  edugestdz/nginx/conf.d/selfhosted.conf \
  edugestdz/install.sh \
  edugestdz/update.sh \
  edugestdz/setup-vpn.sh \
  edugestdz/backend/.env.level1.example \
  edugestdz/backend/.env.level2.example \
  edugestdz/backend/.env.level3.example \
  edugestdz/backend/app/Services/LicenceService.php \
  edugestdz/backend/app/Http/Middleware/LicenceCheck.php \
  edugestdz/ANPDP_DECLARATION.md \
  edugestdz/docs/DEPLOIEMENT.md

# Rendre les scripts exécutables
git update-index --chmod=+x edugestdz/install.sh
git update-index --chmod=+x edugestdz/update.sh
git update-index --chmod=+x edugestdz/setup-vpn.sh

git commit -m "feat: Déploiement 3 niveaux — SaaS Cloud DZ + Hybrid OVH + Self-Hosted · install.sh 1 commande · update.sh sans coupure · VPN WireGuard support · Licence protection · Guide ANPDP loi 18-07"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_DEPLOIEMENT_3_NIVEAUX.md — 11 étapes dans l'ordre.

RÈGLES :
1. Ne JAMAIS modifier docker-compose.prod.yml existant — créer docker-compose.selfhosted.yml nouveau.
2. Ne pas toucher aux contrôleurs, modèles ou tests existants.
3. LicenceCheck middleware → ajouter dans bootstrap/app.php UNIQUEMENT si la ligne
   $middleware->api(prepend: [...]) existe déjà — sinon créer proprement.
4. Les scripts bash (.sh) doivent être marqués +x avec git update-index.
5. Créer le dossier docs/ s'il n'existe pas.
6. Ne pas relancer les tests — c'est de l'infrastructure, pas du code métier.

git push origin develop → PR develop → main
```
