# Guide de Déploiement EduGest DZ — 3 Niveaux

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
