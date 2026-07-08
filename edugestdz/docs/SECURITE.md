# 🛡️ Documentation Sécurité — EduGest DZ
## Pour les auditeurs, partenaires et équipes techniques

---

## Résumé de conformité

| Exigence | Statut | Détail |
|----------|--------|--------|
| Loi 18-07 (ANPDP) | ✅ Préparé | Déclaration à déposer avant données réelles |
| Chiffrement données sensibles | ✅ AES-256-CBC | Colonnes tokens, clés API |
| Authentification forte | ✅ JWT + 2FA TOTP | Obligatoire pour admins |
| Isolation données tenants | ✅ Triple couche | Applicatif + RLS PostgreSQL + Middleware |
| Audit logs immuables | ✅ Merkle SHA3-256 | Falsification détectable mathématiquement |
| Politique mots de passe | ✅ 12 chars min + complexité | Blacklist 40+ mots de passe courants |
| Réponse aux incidents | ✅ Documentée | INCIDENT_RESPONSE_PLAN.md |
| Headers sécurité HTTP | ✅ OWASP complets | CSP, HSTS, X-Frame-Options, etc. |

---

## Les 6 niveaux de sécurité

### Niveau 1 — Fondations
- **JWT Blacklist** : tokens révoqués immédiatement à la déconnexion (Redis + BDD)
- **PostgreSQL RLS** : Row-Level Security sur 40+ tables (filet BDD)
- **Isolation tenant** : `BelongsToTenant` + `TenantIsolationVerifier`
- **Fichiers sécurisés** : URLs signées HMAC expirantes (jamais d'URL permanente publique)

### Niveau 2 — Protection des données
- **Chiffrement colonnes** : `EncryptedString` cast sur tokens Satim, Google OAuth, Firebase
- **MFA obligatoire** : 2FA TOTP requis pour admin et super_admin
- **Brute force** : blocage après 10 tentatives, période 15 min
- **Headers OWASP** : CSP, HSTS, X-Content-Type-Options, Referrer-Policy

### Niveau 3 — Conformité
- **Audit logs signés** : SHA-256 + HMAC exportés quotidiennement
- **Politique MDP** : 12 chars, majuscule, chiffre, spécial, blacklist 40+ mots interdits
- **IP Allowlist** : Super-admin restreint aux IPs connues
- **JWT rotation** : renouvellement programmable avec période de grâce 24h
- **Breach API** : déclaration d'incident avec rappel délai 72h ANPDP

### Niveau 4 — Zero-Trust
- **Risk Score 0-100** : 9 facteurs évalués par requête, fail-secure (exception = 100)
- **Device Fingerprinting** : appareils enregistrés + challenge OTP pour nouveaux appareils
- **RBAC granulaire** : permissions au niveau du champ (ex: enseignant voit notes mais pas salaires)
- **Rate Limiter adaptatif** : quotas différents par rôle/heure/type de route

### Niveau 5 — Détection active
- **Honeypots** : 16 routes leurres + champs pièges formulaires → IP blacklistée 24h
- **Canary Tokens** : tokens fictifs en BDD → si utilisés = preuve de dump BDD
- **SSRF Protection** : bloque les requêtes vers métadonnées cloud et réseau interne
- **SQL Injection Layer** : 18 patterns détectés avant d'atteindre Eloquent
- **Vault Secrets** : secrets hors .env (HashiCorp Vault ou BDD chiffrée en fallback)
- **Insider Threat** : détection volume anormal de téléchargement
- **Dead Man Switch** : alerte si aucun admin ne se connecte en 7 jours

### Niveau 6 — Forteresse
- **Audit Chain Merkle** : SHA3-256 + DB::transaction + chunk(1000) — falsification impossible
- **SIEM** : 5 règles de corrélation (credential stuffing, impossible travel, SQLi coordonnée...)
- **Post-Quantum** : Ed25519 (sodium) avec fallback RSA-4096
- **Kill Switch MPC** : 2 super-admins requis + fenêtre 600s pour activer
- **Supply Chain** : vérification hash composer.lock chaque semaine

---

## Contacts sécurité

Pour signaler une vulnérabilité ou un incident :
- Email sécurité : [à configurer par l'établissement]
- Procédure complète : [INCIDENT_RESPONSE_PLAN.md](../INCIDENT_RESPONSE_PLAN.md)
- ANPDP : www.anpdp.dz (délai légal 72h pour notification breach)
