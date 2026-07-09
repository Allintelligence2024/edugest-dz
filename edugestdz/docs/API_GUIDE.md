# 🔌 Guide API — EduGest DZ
## Pour les développeurs frontend, mobile et intégrateurs

---

## Base URL

```
Production  : https://api.votre-ecole.dz/api/v1
Développement: http://localhost:8000/api/v1
Documentation: http://localhost:8000/api/documentation (Swagger UI)
```

---

## Authentification

### 1. Se connecter (obtenir un token)
```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@ecole-oran.dz",
  "password": "VotreMotDePasse"
}
```

Réponse :
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": { "id": "uuid", "nom": "Benali", "role": "admin" }
  }
}
```

### 2. Utiliser le token
Ajouter sur chaque requête :
```http
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
X-Tenant-ID: uuid-du-tenant
```

### 3. Si 2FA activée
Après le login, vous recevrez :
```json
{ "two_factor_required": true, "temp_token": "xxx" }
```

Vérifier le code TOTP :
```http
POST /api/v1/auth/2fa/verify
{ "temp_token": "xxx", "code": "123456" }
```

### 4. Déconnexion (révoque le token)
```http
POST /api/v1/auth/logout
Authorization: Bearer ...
```

---

## Codes de réponse HTTP

| Code | Signification |
|------|--------------|
| 200 | Succès |
| 201 | Créé avec succès |
| 400 | Requête invalide (validation) |
| 401 | Non authentifié (token manquant ou expiré) |
| 403 | Accès refusé (rôle insuffisant, MFA manquante, tenant erroné) |
| 404 | Ressource non trouvée |
| 410 | Lien expiré (fichiers signés) |
| 422 | Erreur de validation des données |
| 428 | Challenge Zero-Trust requis |
| 429 | Trop de requêtes (rate limit atteint) |
| 503 | Service temporairement indisponible (kill switch actif) |

---

## Codes d'erreur métier

| Code | Signification | Action |
|------|--------------|--------|
| `INVALID_CREDENTIALS` | Email ou mot de passe incorrect | Vérifier les identifiants |
| `ACCOUNT_LOCKED` | Compte verrouillé (trop de tentatives) | Attendre 30 min ou contacter admin |
| `BRUTE_FORCE_BLOCKED` | IP bloquée pour brute force | Attendre 15 min |
| `MFA_REQUIRED` | 2FA non activée (admin) | Activer 2FA dans Paramètres |
| `TOKEN_REVOKED` | Token révoqué (logout autre session) | Se reconnecter |
| `TENANT_MANIPULATION` | Tentative d'accès à un autre tenant | Vérifier X-Tenant-ID |
| `ZERO_TRUST_BLOCKED` | Requête bloquée par risk score | Contacter admin |
| `GLOBAL_LOCKDOWN` | Verrouillage d'urgence actif | Contacter super-admin |

---

## Exemples d'endpoints principaux

### Élèves
```http
GET    /api/v1/eleves              # Liste paginée
GET    /api/v1/eleves/{id}         # Détail
POST   /api/v1/eleves              # Créer
PUT    /api/v1/eleves/{id}         # Modifier
DELETE /api/v1/eleves/{id}         # Supprimer (soft delete)
GET    /api/v1/eleves?search=Benali # Recherche Meilisearch
```

### Notes et bulletins
```http
GET    /api/v1/notes               # Toutes les notes du tenant
POST   /api/v1/evaluations         # Nouvelle évaluation
POST   /api/v1/bulletins/generer   # Générer les bulletins PDF
GET    /api/v1/bulletins/{id}/pdf  # Télécharger un bulletin PDF
```

### Finance
```http
GET    /api/v1/factures            # Toutes les factures
POST   /api/v1/paiements           # Enregistrer un paiement
GET    /api/v1/finance/caisse-jour # Récapitulatif journalier
POST   /api/v1/paiement-en-ligne/initier # Initier paiement Satim
```

### Sécurité (super-admin uniquement)
```http
GET    /api/v1/security/dashboard          # Dashboard sécurité
POST   /api/v1/security/kill-switch/voter  # Vote kill switch
GET    /api/v1/security/siem/rapport       # Rapport SIEM 24h
GET    /api/v1/security/breach/incidents   # Incidents déclarés
```

---

## Pagination

```http
GET /api/v1/eleves?page=2&per_page=25
```

Réponse meta :
```json
{
  "meta": {
    "current_page": 2,
    "per_page": 25,
    "total": 348,
    "last_page": 14
  }
}
```

---

## Rate Limiting

Les headers de rate limit sont inclus dans chaque réponse :
```
X-RateLimit-Limit: 500
X-RateLimit-Remaining: 487
```

Limites par rôle (par minute) :
- `super_admin` : 1000 req/min
- `admin` : 500 req/min
- `enseignant` : 300 req/min
- `parent` : 200 req/min

---

## Documentation interactive (Swagger)

Après lancement du serveur :
```bash
php artisan l5-swagger:generate
```
Accéder à : `http://localhost:8000/api/documentation`

Tous les endpoints sont documentés avec exemples de requête et réponse.
