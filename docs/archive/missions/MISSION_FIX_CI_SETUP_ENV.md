# 🔧 MISSION DEEPSEEK — Fix CI "Setup environment" exit code 1
## EduGest DZ · Branche : develop · 9 Juillet 2026
## CI Run #198 — Step "Setup environment" — exit code 1

---

## DIAGNOSTIC EXACT (lu dans GitHub)

### Cause racine trouvée — Le `.env.example` a des commentaires INLINE

Le CI fait :
```bash
cp .env.example .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=edugestdz_test|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=EduGest@2026!|" .env
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" .env
php artisan key:generate
php artisan jwt:secret --force
```

Le `.env.example` actuel (lu) contient des commentaires INLINE collés aux valeurs :
```dotenv
APP_ENV=local# local | staging | production     ← # directement sur la ligne
APP_KEY=# Générer avec: php artisan key:generate ← # sur la ligne de valeur
DB_PASSWORD=# Minimum 16 caractères, aléatoire   ← # sur la ligne
JWT_SECRET=# 64+ caractères aléatoires            ← # sur la ligne
JWT_TTL=60# Durée de vie du token en minutes      ← # collé à la valeur
APP_TIMEZONE=Africa/Algiers# NE PAS CHANGER       ← # collé à la valeur
```

**Conséquence :**
- `php artisan key:generate` plante car `APP_KEY=# Générer...` n'est pas une clé valide
- Laravel essaie de parser `# Minimum 16 caractères` comme valeur de DB_PASSWORD
- Exit code 1 à l'étape "Setup environment" avant même les migrations

### Cause secondaire (déjà résolue) — Sentry config invalide
Le commit 48e21bd a corrigé `capture_unhandled_rejections` (option inconnue dans laravel-sentry).
Mais le `.env.example` reste le problème principal.

---

## RÈGLES ABSOLUES
1. 0 régression — tests verts doivent rester verts
2. Ne pas modifier la logique des `sed` dans ci.yml — corriger le .env.example
3. Les commentaires doivent être sur des lignes SÉPARÉES (pas inline)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin develop
```

---

## FIX UNIQUE — Réécrire .env.example sans commentaires inline

**Remplacer ENTIÈREMENT** : `edugestdz/backend/.env.example`

```dotenv
# ══════════════════════════════════════════════════════════
# EDUGEST DZ — Configuration complète
# Copier ce fichier en .env et remplir les valeurs
# JAMAIS committer le .env réel dans Git
# ══════════════════════════════════════════════════════════

# ── Application ───────────────────────────────────────────
# local | staging | production
APP_NAME="EduGest DZ"
APP_ENV=local
# Générer avec: php artisan key:generate
APP_KEY=
# false en production OBLIGATOIRE
APP_DEBUG=true
APP_URL=http://localhost
# Pour identifier les releases dans Sentry
APP_VERSION=1.0.0

# ── Base de données PostgreSQL ────────────────────────────
# NE PAS utiliser le user 'postgres' en production
# Créer un user dédié: CREATE USER edugest_user WITH PASSWORD '...'
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
# Minimum 16 caractères, aléatoire
DB_PASSWORD=

# ── Redis ──────────────────────────────────────────────────
# Utilisé pour: cache, sessions, JWT blacklist, rate limiting
REDIS_HOST=127.0.0.1
# Obligatoire en production
REDIS_PASSWORD=
REDIS_PORT=6379

# ── JWT Authentication ────────────────────────────────────
# Générer avec: php artisan jwt:secret
# Rotation: php artisan edugest:jwt-rotate
# 64+ caractères aléatoires
JWT_SECRET=
# Durée de vie du token en minutes
JWT_TTL=60

# ── Meilisearch ───────────────────────────────────────────
MEILISEARCH_HOST=http://localhost:7700
# Master key Meilisearch
MEILISEARCH_KEY=

# ── Email ─────────────────────────────────────────────────
# Changer pour un vrai SMTP en production
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@votre-ecole.dz
MAIL_FROM_NAME="${APP_NAME}"

# ── SMS (Twilio) ──────────────────────────────────────────
# Créer un compte sur twilio.com → SMS pour Algérie (+213)
TWILIO_SID=
TWILIO_TOKEN=
# Numéro Twilio format international (+1...)
TWILIO_FROM=

# ── Firebase (Notifications push mobile) ─────────────────
# Télécharger le JSON depuis Firebase Console → Paramètres projet
FIREBASE_PROJECT_ID=
FIREBASE_PRIVATE_KEY=
FIREBASE_CLIENT_EMAIL=

# ── Satim (Paiement CIB/Dahabia) ─────────────────────────
# Contacter votre banque partenaire Satim pour les credentials
# TEST : utiliser https://test.satim.dz
# PROD : utiliser https://satim.dz (après signature contrat)
SATIM_BASE_URL=https://test.satim.dz/payment/rest
# Fourni par Satim
SATIM_USERNAME=
# Fourni par Satim
SATIM_PASSWORD=
# Fourni par Satim
SATIM_TERMINAL=

# ── WhatsApp Business API (Meta) ─────────────────────────
# Créer une application sur developers.facebook.com
WHATSAPP_TOKEN=
WHATSAPP_PHONE_ID=
WHATSAPP_WEBHOOK_SECRET=

# ── Google Classroom OAuth2 ───────────────────────────────
# Créer des credentials OAuth2 sur console.cloud.google.com
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/classroom/callback"

# ── Alertes Sécurité (Telegram) ──────────────────────────
# Créer un bot: https://t.me/BotFather → /newbot
# Obtenir votre chat_id: https://t.me/userinfobot
TELEGRAM_BOT_TOKEN=
TELEGRAM_SECURITY_CHAT_ID=

# ── HashiCorp Vault (Optionnel) ───────────────────────────
# Si vide → fallback sur BDD chiffrée (moins sécurisé mais fonctionnel)
# Ex: https://vault.votre-ecole.dz:8200
VAULT_ADDR=
VAULT_TOKEN=

# ── Sécurité ──────────────────────────────────────────────
# IPs autorisées pour le Super-Admin (vide = pas de restriction)
# Format: IP1,IP2,CIDR ou vide pour dev
SUPER_ADMIN_ALLOWED_IPS=
# Email pour alertes critiques de sécurité
SECURITY_ALERT_EMAIL=

# ── Surveillance Dahua ────────────────────────────────────
# Secret pour vérifier les webhooks Dahua
DAHUA_WEBHOOK_SECRET=

# ── Monitoring & Observabilité ────────────────────────────
# Sentry.io — Tracking erreurs production
# Créer un compte sur sentry.io (GRATUIT jusqu'à 5000 events/mois)
# → New Project → Laravel → Copier le DSN ici
# Laisser vide pour désactiver (dev local, CI)
SENTRY_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1

# ── Configuration Algérie ─────────────────────────────────
# NE PAS CHANGER — fuseau horaire officiel Algérie
APP_TIMEZONE=Africa/Algiers
APP_LOCALE=fr
```

---

## VÉRIFICATION LOCALE AVANT PUSH

```bash
cd edugestdz/backend

# Simuler exactement ce que fait le CI
cp .env.example .env.test_ci

sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|"              .env.test_ci
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=edugestdz_test|"  .env.test_ci
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=EduGest@2026!|"   .env.test_ci
sed -i "s|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|"         .env.test_ci

# Vérifier que les valeurs sont correctes (pas de commentaires inline)
grep "^DB_HOST="     .env.test_ci   # → DB_HOST=127.0.0.1
grep "^DB_DATABASE=" .env.test_ci   # → DB_DATABASE=edugestdz_test
grep "^DB_PASSWORD=" .env.test_ci   # → DB_PASSWORD=EduGest@2026!
grep "^APP_KEY="     .env.test_ci   # → APP_KEY=  (vide, sans #)
grep "^JWT_SECRET="  .env.test_ci   # → JWT_SECRET=  (vide, sans #)
grep "^JWT_TTL="     .env.test_ci   # → JWT_TTL=60  (sans # collé)

# Nettoyer
rm .env.test_ci

echo "✅ .env.example propre — CI devrait passer"
```

---

## COMMIT ET PUSH

```bash
git add edugestdz/backend/.env.example
git commit -m "fix(ci): .env.example — supprimer commentaires inline collés aux valeurs

Le CI faisait 'cp .env.example .env' puis 'php artisan key:generate'.
Les commentaires inline (APP_KEY=# texte, JWT_TTL=60# texte) causaient :
  - APP_KEY invalide → Laravel crash au démarrage
  - JWT_SECRET invalide → jwt:secret --force échoue
  - DB_PASSWORD avec texte → PostgreSQL connexion refusée
Exit code 1 à l'étape 'Setup environment' du CI.

Fix : tous les commentaires déplacés sur des lignes SÉPARÉES (# en début de ligne).
Aucune valeur ne contient plus de # inline."

git push origin develop
```

---

## RÉSULTAT ATTENDU

```
CI Run #199 :
  ✅ Setup PHP 8.2
  ✅ Install dependencies
  ✅ Setup environment    ← plus d'exit code 1
  ✅ Run migrations
  ✅ Run tests → 607+ ✅
  ✅ Run tests with coverage
```
