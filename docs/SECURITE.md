# 🛡️ Documentation Sécurité — EduGest DZ
## Pour les auditeurs, partenaires et équipes techniques

> **État réel au 10 sept. 2026** : ce document distingue ce qui est **en
> production** de ce qui reste ouvert. Le référentiel est le pentest Sprint 6
> (7 constats C1-C7, 3 corrigés) : [`SPRINT6_EXPLOITATION.md`](SPRINT6_EXPLOITATION.md) § 3.

---

## Résumé de conformité

| Exigence | Statut | Détail |
|----------|--------|--------|
| Loi 18-07 (ANPDP) | ✅ Outils livrés | Registre des traitements, consentements parentaux tracés, rétention automatique — déclaration ANPDP à déposer par chaque établissement |
| Chiffrement données sensibles | ✅ AES-256-CBC | Colonnes tokens, clés API |
| Authentification forte | ✅ JWT + 2FA TOTP | Obligatoire sur les routes super-admin ; au choix pour les admins |
| Isolation données tenants | ✅ Triple couche | Applicatif + RLS PostgreSQL + Middleware |
| Audit logs immuables | ✅ Merkle + HMAC-SHA-256 | Falsification détectable mathématiquement ; vérification quotidienne automatique |
| Politique mots de passe | ✅ 12 chars min + complexité | Blacklist 40+ mots de passe courants |
| Réponse aux incidents | ✅ Documentée | INCIDENT_RESPONSE_PLAN.md |
| Headers sécurité HTTP | ✅ OWASP complets | CSP, HSTS, X-Frame-Options, etc. |

---

## Les 6 niveaux de sécurité

### Niveau 1 — Fondations
- **Révocation JWT** : les tokens sont invalidés à la déconnexion (blacklist du garde JWT)
- ⚠️ **Reste ouvert** (pentest Sprint 6, constat C2) : la seconde blacklist maison (`jwt_blacklist` + middleware `jwt.blacklist`) n'est appliquée à aucune route — le « verrouillage d'urgence » de la breach response n'invalide donc **pas** les JWT existants. Correctif décrit dans `SPRINT6_EXPLOITATION.md` § 3
- **PostgreSQL RLS** : Row-Level Security sur 40+ tables (filet BDD)
- **Isolation tenant** : `BelongsToTenant` + `TenantIsolationVerifier`
- **Fichiers sécurisés** : URLs signées HMAC expirantes (jamais d'URL permanente publique)

### Niveau 2 — Protection des données
- **Chiffrement colonnes** : `EncryptedString` cast sur tokens Satim, Google OAuth, Firebase
- **MFA** : 2FA TOTP/SMS — obligatoire pour les super-admins (middleware `mfa` sur les routes `super-admin/*`), recommandée pour les admins d'établissement
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
- **Device Fingerprinting** : empreinte par appareil (IP, en-têtes, user-agent)
- ⚠️ **Reste ouvert** (pentest Sprint 6, constat C3) : le mode `zero.trust:strict` (428 + challenge) n'est branché sur aucune route et le flux de vérification du challenge est incomplet — le score est aujourd'hui calculé, journalisé et signalé à Sentry (> 50), mais pas enforcé
- **RBAC granulaire** : permissions au niveau du champ (ex: enseignant voit notes mais pas salaires)
- **Rate Limiter adaptatif** : quotas différents par rôle/heure/type de route

### Niveau 5 — Détection active
- **Honeypots** : 16 routes leurres + champs pièges formulaires → IP blacklistée 24h
- **Canary Tokens** : tokens fictifs en BDD → si utilisés = preuve de dump BDD
- **SSRF Protection** : bloque les requêtes vers métadonnées cloud et réseau interne
- **SQL Injection Layer** : 18 patterns détectés avant d'atteindre Eloquent
- **Vault Secrets** : secrets hors .env (HashiCorp Vault ou BDD chiffrée en fallback)
- **Insider Threat** : détection volume anormal de téléchargement
- **Dead Man Switch** : notification à 80 jours d'inactivité admin, signalement à 90 jours (désactivation manuelle uniquement)

### Niveau 6 — Forteresse
- **Audit Chain Merkle** : HMAC-SHA-256 (clé dédiée hors APP_KEY, rotation par version) + DB::transaction — vérifiée chaque nuit à 03 h 30 (`audit:verify`)
- **SIEM** : 5 règles de corrélation (credential stuffing, impossible travel, SQLi coordonnée...)
- **Crypto asymétrique** : Ed25519 (libsodium) avec fallback RSA-4096 — cryptographie classique robuste, **pas** post-quantique (assumé, cf. en-tête du service)
- **Kill Switch MPC** : 2 admins distincts requis (`role:admin`, pentest C1) + fenêtre 600 s — coupe le service pour tous les tenants
- **Supply Chain** : vérification hash composer.lock chaque semaine

---

## Contacts sécurité

Pour signaler une vulnérabilité ou un incident :
- Email sécurité : [à configurer par l'établissement]
- Procédure complète : [guides/INCIDENT_RESPONSE_PLAN.md](guides/INCIDENT_RESPONSE_PLAN.md)
- ANPDP : www.anpdp.dz (délai légal 72h pour notification breach)
