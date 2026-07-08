# 🏗️ Architecture Technique — EduGest DZ
## Document technique · Juillet 2026

---

## Vue d'ensemble

EduGest DZ suit une architecture **monolithique modulaire** (Laravel Modular Monolith).
Pas de microservices — un seul déploiement, plus simple à maintenir pour une startup.
La modularité est obtenue via les Services et le Module Manager (activation/désactivation par tenant).

```
[Client Web]──►[React 18 SPA]──►[API Laravel 11]──►[PostgreSQL 16]
[Client Mobile]►[React Native]──►     ↑                   ↑
                                  [Redis 7]         [Meilisearch]
```

---

## Multi-tenancy

Chaque établissement est un **tenant** isolé. L'isolation est triple :

### Niveau 1 — Applicatif (BelongsToTenant trait)
```php
// Chaque modèle utilise ce trait
// Ajoute automatiquement WHERE tenant_id = ? sur toutes les requêtes
use BelongsToTenant;
```

### Niveau 2 — Base de données (PostgreSQL RLS)
```sql
-- Politique appliquée sur 40+ tables
CREATE POLICY tenant_isolation_policy ON eleves
USING (tenant_id::text = current_setting('app.current_tenant_id', true));
```

### Niveau 3 — Middleware (TenantIsolationVerifier)
- Vérifie que le header `X-Tenant-ID` correspond au tenant du token JWT
- Bloque les tentatives de manipulation cross-tenant

---

## Flux d'une requête API

```
Requête HTTP
    ↓
[1] KillSwitchMiddleware       → Vérifie si le kill switch est actif (503 si oui)
    ↓
[2] HoneypotRouteMiddleware    → Route leurre ? → 404 + IP blacklistée
    ↓
[3] SqlInjectionDetector       → Pattern SQL dangereux ? → 400 + IP bannie
    ↓
[4] JwtBlacklistCheck          → Token révoqué ? → 401
    ↓
[5] ZeroTrustMiddleware        → Risk Score → [0-50: OK] [51-75: log] [76+: block]
    ↓
[6] auth:api                   → JWT valide ? → 401 sinon
    ↓
[7] ResolveTenant              → Résoudre tenant + SET LOCAL PostgreSQL
    ↓
[8] TenantIsolationVerifier    → X-Tenant-ID cohérent ? → 403 si manipulation
    ↓
[9] MfaRequired                → Admin sans 2FA ? → 403
    ↓
[10] IntelligentRateLimiter    → Rate limit adaptatif
    ↓
Controller → Service → Model → PostgreSQL (+ RLS au niveau BDD)
    ↓
AuditChainService              → Enregistrement immuable Merkle
    ↓
Réponse JSON standardisée
```

---

## Chaîne de sécurité — Détail

### Risk Score Engine
Chaque requête reçoit un score de risque 0-100 :

| Facteur | Points |
|---------|--------|
| IP jamais vue pour cet utilisateur | +40 |
| Pays inhabituel (non-Algérie) | +30 |
| Appareil non reconnu | +25 |
| Heure anormale (2h-5h) | +20 |
| >50 requêtes en 5 minutes | +20 |
| >3 erreurs 403 en 10 minutes | +15 |
| >3 logins échoués | +15 |
| User-Agent botlike (curl, python...) | +10 |
| Volume de données suspect | +10 |

Actions : 0-50 → OK · 51-75 → Loggé · 76-90 → Bloqué · 91-100 → Compte verrouillé 30min

### Audit Chain (Merkle Tree)
Chaque opération sensible (CREATE/UPDATE/DELETE) est enregistrée dans une chaîne :
```
Bloc N : {contenu} + hash(Bloc N-1) → hash_merkle(N) → HMAC-SHA3-256
```
Toute modification d'un log invalide mathématiquement tous les blocs suivants.

---

## Schéma de la base de données

→ [Voir docs/BASE_DE_DONNEES.md](BASE_DE_DONNEES.md)

---

## Schedulers (tâches planifiées)

| Fréquence | Tâche |
|-----------|-------|
| Toutes les 5 min | SIEM corrélation événements |
| Quotidien 1h | Vérification intégrité Audit Chain |
| Quotidien 2h | Export audit logs signé HMAC |
| Quotidien 7h | Alertes stock bas + entretien préventif |
| Lun-Ven 8h30 | SMS absences parents |
| Quotidien 9h | Relances factures impayées + Dead Man Switch |
| 1er du mois 6h | Génération factures transport + cantine |
| Dimanche 3h | Nettoyage JWT blacklist expirée |
| Lundi 4h | Vérification supply chain (intégrité dépendances) |
| Lundi 5h | Génération séances semaine |
