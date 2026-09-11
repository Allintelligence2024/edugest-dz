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
        \App\Models\User::updateOrCreate(
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
