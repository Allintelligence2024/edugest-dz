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
Chaque requête authentifiée reçoit un score de risque 0-100 (grille réelle
du code, `RiskScoreEngine`) :

| Facteur | Points |
|---------|--------|
| Appareil inconnu (empreinte non enregistrée) | +40 |
| Pas d'en-tête `Sec-Ch-Ua` | +15 |
| Nuit (hors 6 h-22 h) | +15 |
| Pas d'en-tête `Accept-Language` | +10 |
| IP privée/réservée | +5 |
| En-tête proxy présent (X-Forwarded-For…) | +5 |
| Exception pendant le calcul | 100 (fail-secure) |

Actions réelles : le score est journalisé à chaque requête et signalé à
Sentry au-delà de 50. Le mode `zero.trust:strict` (428 + challenge
appareil) existe dans le middleware mais n'est **pas encore branché sur
une route** — l'enforcement est un reste ouvert (pentest Sprint 6,
constat C3). La calibration derrière un proxy reste à faire (constat C5 :
pas de `trustProxies`, +10 systématiques derrière nginx).

### Audit Chain (Merkle Tree)
Chaque opération sensible (CREATE/UPDATE/DELETE) est enregistrée dans une chaîne :
```
Bloc N : {contenu} + hash(Bloc N-1) → HMAC-SHA-256 (clé dédiée hors APP_KEY)
```
Toute modification d'un log invalide mathématiquement tous les blocs
suivants. La chaîne est vérifiée chaque nuit à 03 h 30 (`audit:verify`).

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
