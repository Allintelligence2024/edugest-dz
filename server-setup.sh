#!/bin/bash
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# server-setup.sh — Installation initiale sur VPS
# À exécuter UNE SEULE FOIS en root sur le serveur
# Testé sur Ubuntu 22.04 LTS (OVH/Hetzner)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
set -e

DOMAIN="edugestdz.dz"        # ← remplacer par votre domaine
EMAIL="admin@edugestdz.dz"   # ← votre email pour Let's Encrypt
APP_USER="edugest"

echo "🔧 Configuration serveur EduGest DZ"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── 1. Mise à jour système ──
apt-get update && apt-get upgrade -y

# ── 2. Installer Docker ──
echo "🐳 Installation Docker..."
apt-get install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# ── 3. Créer l'utilisateur applicatif ──
echo "👤 Création utilisateur $APP_USER..."
useradd -m -s /bin/bash $APP_USER
usermod -aG docker $APP_USER
usermod -aG sudo $APP_USER

# ── 4. Firewall UFW ──
echo "🔒 Configuration firewall..."
ufw --force enable
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw status

# ── 5. Cloner le repo ──
echo "📥 Clonage du repo..."
su - $APP_USER -c "
  git clone https://github.com/Allintelligence2024/edugest-dz.git /home/$APP_USER/edugest-dz
  cd /home/$APP_USER/edugest-dz/edugestdz
"

# ── 6. Certificat SSL Let's Encrypt (pré-déploiement) ──
echo "🔐 Certificat SSL Let's Encrypt..."
docker run --rm -p 80:80 \
  -v /home/$APP_USER/certbot/www:/var/www/certbot \
  -v /home/$APP_USER/certbot/conf:/etc/letsencrypt \
  certbot/certbot certonly \
    --standalone \
    --email $EMAIL \
    --agree-tos \
    --no-eff-email \
    -d $DOMAIN \
    -d www.$DOMAIN

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Serveur prêt !"
echo ""
echo "Prochaines étapes :"
echo "  1. su - $APP_USER"
echo "  2. cd ~/edugest-dz/edugestdz"
echo "  3. cp backend/.env.production.example backend/.env"
echo "  4. nano backend/.env  ← remplir toutes les valeurs"
echo "  5. chmod +x deploy.sh && ./deploy.sh --fresh"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
