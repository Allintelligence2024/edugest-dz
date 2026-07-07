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
