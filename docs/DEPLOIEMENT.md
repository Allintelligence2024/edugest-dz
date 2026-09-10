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

**Pour :** Zones avec connexion instable · Groupes d'écoles · Établissements avec IT interne
**Conformité :** Loi 18-07 ✅ · Données 100% sur site ✅

> ⚠️ **AVERTISSEMENT HONNÊTE** : Ce mode n'est PAS "plug and play".
> Il nécessite un responsable IT compétent et un serveur dédié sous Ubuntu 22.04.
> La durée d'installation réelle est **45-90 minutes** (pas "10 minutes").
> Sur un vieux PC Windows ou Ubuntu < 20.04 → non supporté.

### Prérequis OBLIGATOIRES (vérifier avant d'acheter)

| Prérequis | Minimum | Recommandé | Obligatoire |
|-----------|---------|------------|-------------|
| OS | Ubuntu 22.04 LTS 64-bit | Ubuntu 22.04 LTS | **Oui** |
| RAM | 6 GB | 8 GB | **Oui — 4 GB insuffisant** |
| CPU | 2 cœurs | 4 cœurs | Min. 2 |
| Stockage | 60 GB SSD | 120 GB SSD NVMe | SSD obligatoire |
| Connexion | 10 Mbps | 20 Mbps+ | Pour l'installation |
| Docker | 24+ | 24+ | **Oui** |
| Connaissance IT | Niveau admin Linux | — | **Obligatoire** |

**❌ NON SUPPORTÉ :**
- Windows (toutes versions)
- Ubuntu 18.04, 20.04
- CentOS, Fedora, Alpine
- Machines avec < 4 GB RAM
- Serveurs partagés (hébergement mutualisé)

### Services installés automatiquement

L'installation déploie les services suivants via Docker Compose :

```
┌────────────────────────────────────────────────────────┐
│  nginx (reverse proxy + SSL)        Port 80/443        │
│  php-fpm (Laravel 11 app)          Port 9000 interne   │
│  postgresql:16 (base de données)   Port 5432 interne   │
│  redis:7 (cache + sessions)        Port 6379 interne   │
│  meilisearch:1.8 (recherche)       Port 7700 interne   │
│  worker (queue jobs Laravel)       Supervisord          │
└────────────────────────────────────────────────────────┘
Total : ~3-4 GB RAM utilisés en fonctionnement normal
```

### Installation (durée réelle : 45-90 minutes)

```bash
# ── Étape 1 : Préparer le serveur (15-20 min) ──────────────
# Sur Ubuntu 22.04 fraîchement installé en root :

# Mettre à jour le système
apt update && apt upgrade -y

# Installer Docker
curl -fsSL https://get.docker.com | bash
usermod -aG docker $USER

# Installer Git
apt install -y git curl nano

# ── Étape 2 : Cloner le projet (5-10 min selon connexion) ──
git clone https://github.com/Allintelligence2024/edugest-dz.git /opt/edugestdz
cd /opt/edugestdz/edugestdz

# ── Étape 3 : Configurer (10-15 min) ───────────────────────
cp backend/.env.level3.example backend/.env
nano backend/.env
# Remplir OBLIGATOIREMENT :
#   APP_URL=https://votre-ecole.dz  (ou http://IP-SERVEUR)
#   DB_PASSWORD=MotDePasseComplexe16Chars
#   JWT_SECRET=(généré automatiquement à l'étape suivante)

# ── Étape 4 : Déployer (15-30 min — téléchargement images) ─
bash install.sh
# → Télécharge les images Docker (~2-3 GB selon vitesse)
# → Génère les clés (APP_KEY, JWT_SECRET)
# → Lance les migrations (60+ tables)
# → Importe le curriculum algérien
# → Configure Nginx + SSL (Let's Encrypt si domaine configuré)

# ── Étape 5 : Créer le premier compte (5 min) ─────────────
docker compose exec app php artisan db:seed --class=InitialProductionSeeder
# → Affiche le mot de passe temporaire du super_admin
# → CHANGER IMMÉDIATEMENT après la première connexion
```

### Vérification post-installation

```bash
# Test smoke — doit retourner "healthy"
curl http://localhost/api/health | python3 -m json.tool

# Vérifier tous les services
docker compose ps
# Tous doivent être "Up"

# Voir les logs si problème
docker compose logs app --tail=50
```

### Mise à jour (5-15 minutes)

```bash
cd /opt/edugestdz/edugestdz
bash update.sh
# → Sauvegarde automatique BDD → Pull nouvelles images → Migrations → Redémarrage
```

### Support à distance

```bash
bash setup-vpn.sh
# → Génère une config WireGuard
# → Partager le fichier généré avec support@edugestdz.dz
```

### Matériel recommandé (prix Algérie Juillet 2026)

| Option | Matériel | Prix estimé | Pour |
|--------|----------|-------------|------|
| **Minimum** | PC reconditionné i5 · 8GB RAM · 256GB SSD | ~45 000-60 000 DA | < 100 élèves |
| **Standard** | Intel NUC Gen 12 · 16GB RAM · 512GB SSD | ~90 000-120 000 DA | < 300 élèves |
| **Groupe** | Mini-tour Xeon · 32GB RAM · 1TB SSD NVMe | ~160 000-200 000 DA | < 1000 élèves |

### Prix d'installation et support

- Installation + configuration + formation admin : **80 000 DA** (une fois)
- Abonnement support mensuel : **5 000 DA/mois** (mises à jour + assistance à distance)
- Formation directeur (1 journée sur site) : **15 000 DA** (optionnel)

> 💡 **Conseil** : Pour la majorité des écoles algériennes, le **Niveau 1 (SaaS Cloud DZ)**
> est beaucoup plus simple et moins cher sur le long terme. Le Self-Hosted est recommandé
> uniquement si vous avez un IT interne compétent et une raison impérative d'héberger localement.
