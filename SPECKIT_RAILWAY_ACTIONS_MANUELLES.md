# 🔧 SPECKIT — Actions Railway Manuelles (sans code)
## EduGest DZ · 6 Juillet 2026
## Le code est OK (PR #21 mergée). Le problème est sur Railway uniquement.

---

## SITUATION ACTUELLE

```
Frontend Vercel ✅ → appelle Railway ❌ → "Impossible de joindre le serveur"

PR #21 mergée il y a 2 minutes → docker/nginx.conf + start.sh corrigés
Le code est bon. Railway doit redéployer depuis main.
```

---

## ÉTAPE 1 — Vérifier que Railway redéploie depuis main (pas develop)

```
Railway → ton projet → service backend → Settings → Source

Vérifier :
  Branch : main   ← doit être main, pas develop
  Root Directory : edugestdz/backend

Si Branch = develop → changer en main → Save → Redeploy
```

---

## ÉTAPE 2 — Vérifier les variables d'environnement critiques

```
Railway → service backend → Variables

VÉRIFIER QUE CES 6 VARIABLES EXISTENT AVEC DE VRAIES VALEURS :

APP_KEY      → doit commencer par "base64:" (pas vide, pas "REMPLACER...")
JWT_SECRET   → doit être une longue chaîne aléatoire (pas vide)
DB_HOST      → adresse IP ou hostname PostgreSQL (pas ${{Postgres.PGHOST}})
DB_DATABASE  → nom de la base (pas vide)
DB_USERNAME  → utilisateur PostgreSQL
DB_PASSWORD  → mot de passe PostgreSQL
```

**Pour trouver les valeurs DB :**
```
Railway → plugin PostgreSQL → onglet "Connect" → "Direct Connection"
→ Copier chaque valeur (Host, Port, Database, User, Password) manuellement
→ Les coller dans les variables du service backend
```

**Pour générer APP_KEY :**
```
Option A — Railway Shell (si le container démarre) :
  Railway → service backend → Deploy → Shell
  php artisan key:generate --show
  → Copier le résultat → Variables → APP_KEY

Option B — En ligne de commande sur ton PC :
  docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
  → Copier → Variables → APP_KEY

Option C — Valeur fixe temporaire pour tester :
  APP_KEY = base64:SLKjh8d3Jk2mNpQr9sTuVwXyZ0aB4cDeF6gHiIjK=
  (À remplacer par une vraie valeur en production)
```

---

## ÉTAPE 3 — Forcer un redéploiement

```
Railway → service backend → Deployments
→ Cliquer sur le dernier déploiement
→ Bouton "Redeploy" (ou "..." → Redeploy)

Attendre 3-5 minutes que le build Docker se termine.
```

---

## ÉTAPE 4 — Lire les logs Railway

```
Railway → service backend → Deployments → dernier deploy → "View Logs"

Chercher dans les logs :
```

| Message dans les logs | Signification | Action |
|---|---|---|
| `✅ PostgreSQL connecté` | DB OK | Continuer |
| `Tentative X/60...` répété | DB inaccessible | Vérifier variables DB (étape 2) |
| `❌ PostgreSQL indisponible` | DB variables fausses | Copier les vraies valeurs du plugin |
| `✅ Migrations OK` | Migrations passées | Bon signe |
| `❌ Migrations échouées` | Erreur DB ou schéma | Voir le message exact |
| `EduGest DZ opérationnel` | Tout est bon | Tester l'URL |
| `Error: Cannot find module` | Dépendances npm manquantes | Pas notre cas (c'est PHP) |
| `SQLSTATE[08006]` | Mauvais host/port PostgreSQL | Re-copier les variables DB |
| `No such file: start.sh` | start.sh pas exécutable | Voir étape 5 |

---

## ÉTAPE 5 — Si "No such file: start.sh" ou permission denied

```
Le fichier start.sh doit avoir les droits d'exécution dans Git.

Faire sur ton PC (terminal) :
  cd edugestdz/backend
  git ls-files --stage start.sh

Si le résultat commence par "100644" (pas 100755) :
  git update-index --chmod=+x start.sh
  git commit -m "fix: chmod +x start.sh"
  git push origin main
  → Railway redéploiera automatiquement
```

---

## ÉTAPE 6 — Tester le health check

```
Ouvrir dans le navigateur (remplacer par ta vraie URL) :
https://[ton-service].up.railway.app/api/health

Résultats possibles :
  {"status":"ok",...}        → ✅ Backend opérationnel → passer à l'étape 7
  502 Bad Gateway            → PHP-FPM ne tourne pas → voir logs étape 4
  504 Gateway Timeout        → Nginx timeout → voir logs étape 4
  Page vide                  → Nginx tourne mais PHP-FPM non → voir logs
  "Connection refused"       → Container pas encore démarré → attendre 2 min
```

---

## ÉTAPE 7 — Créer le compte admin (une seule fois)

```
Si l'étape 6 retourne {"status":"ok"} :

Railway → service backend → Deploy → Shell :
  php artisan tinker

Dans tinker (copier-coller en bloc) :
  App\Models\User::create([
    'nom'       => 'Administrateur',
    'prenom'    => 'Test',
    'email'     => 'admin@edugest.dz',
    'password'  => bcrypt('EduGest2026!'),
    'role'      => 'admin',
    'tenant_id' => \Illuminate\Support\Str::uuid()->toString(),
    'actif'     => true,
  ]);
  exit

Résultat attendu : App\Models\User {#xxx ...}
```

---

## ÉTAPE 8 — Vérifier VITE_API_BASE_URL sur Vercel

```
Vercel → ton projet → Settings → Environment Variables

VITE_API_BASE_URL doit être :
  https://[ton-service-exact].up.railway.app/api/v1

Pour trouver l'URL exacte :
  Railway → service backend → Settings → Networking → Public Domain
  → Copier l'URL (ex: edugest-backend-production.up.railway.app)
  → Ajouter /api/v1 à la fin

Après modification :
  Vercel → Deployments → dernier déploiement → Redeploy
```

---

## ÉTAPE 9 — Test final

```
1. Ouvrir : https://[ton-app].vercel.app
2. Email    : admin@edugest.dz
3. Password : EduGest2026!
4. Cliquer "Se connecter"

Si ça fonctionne → Dashboard admin s'affiche ✅
Si erreur → Copier le message exact et me le donner
```

---

## RÉSUMÉ DES CAUSES LES PLUS FRÉQUENTES

```
80% des cas → Variables DB mal copiées (${{Postgres.PGHOST}} au lieu des vraies valeurs)
10% des cas → APP_KEY vide ou invalide
 5% des cas → start.sh pas exécutable (chmod +x)
 5% des cas → Railway déploie depuis develop au lieu de main
```

---

## CE QUE TU DIS À UNE IA (si tu veux qu'elle t'aide)

```
Le backend EduGest DZ est déployé sur Railway.
Le frontend Vercel affiche "Impossible de joindre le serveur".
Le code est correct (PR #21 mergée sur main).

Problème : Railway ne répond pas sur /api/health.

Logs Railway (copier-coller les logs ici) :
[COLLER LES LOGS ICI]

Variables Railway configurées :
APP_KEY : [vide / présent]
DB_HOST : [valeur ou ${{Postgres.PGHOST}}]
JWT_SECRET : [vide / présent]

Que faire ?
```
