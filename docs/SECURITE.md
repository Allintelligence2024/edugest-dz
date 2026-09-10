# Sécurité EduGest DZ

## Niveau 6 - Architecture de Sécurité de Niveau Bancaire

EduGest DZ implémente une sécurité de niveau bancaire à 6 niveaux :

| Niveau | Protection |
|--------|------------|
| **1** | JWT Blacklist Redis + PostgreSQL RLS + Isolation tenant |
| **2** | Chiffrement colonnes sensibles + MFA obligatoire admins |
| **3** | Audit logs HMAC-SHA256 + Password Policy + IP Allowlist |
| **4** | Zero-Trust Engine + Device Fingerprinting + Risk Score 0-100 |
| **5** | Honeypots + Canary Tokens + SSRF Protection + Vault Secrets |
| **6** | Audit Chain Merkle SHA3 + SIEM + Post-Quantum + Kill Switch MPC |

### Principes Directeurs

- **Zero-Trust** : aucun accès par défaut, vérification à chaque requête
- **Defense en profondeur** : plusieurs couches de sécurité
- **Fail-closed** : tout contexte tenant absent → zéro résultat (pas d'exposition de données)
- **Audit complet** : traçabilité complète de toutes les actions sensibles
- **Principe du moindre privilège** : accès minimums requis

### Modèle Tenant (Fail-Closed)

Chaque requête base de données doit reproduire le scope tenant via :
- `where('tenant_id', $tenantId)` si tenant configuré
- `whereRaw('1 = 0')` si aucun contexte tenant (réfus strict)

### Mot de Passe

- Politique de mot de passe obligatoire (longueur minimum 8, complexité)
- Hashing Argon2id
- Interdiction des mots de passe compromis (via HaveIBeenPwned API)
- Reset mot de passe avec lien temporisé (5 minutes maximum)

### 2FA (Two-Factor Authentication)

- TOTP (Google Authenticator) ou SMS
- Codes de récupération à usage unique
- Verrouillage compte après X échecs (15 minutes)
- Sessions de rafraîchissement de token JWT

### Données Personnelles (Loi 18-07 ALN)

- Conservation limitée des données
- Droit à l'oubli
- Journalisation des accès (qui, quand, depuis où)
- Déclaration à l'ANPDP obligatoire

### Gestion des Secrets

- Secrets stockés dans Vault ou PostgreSQL secrets
- Jamais en dur dans le code ou .env
- Rotation régulière des clés
- Audit d'accès aux secrets

## Points d'Attention Sécurité

- **XSS Prevention** : échappement systématique des sorties HTML
- **CSRF Protection** : tokens obligatoires pour les formulaires
- **SQL Injection** : utilisation de requêtes paramétrées uniquement
- **File Upload Validation** : types MIME restrictifs, scan antivirus
- **API Rate Limiting** : par IP et par utilisateur
- **Backup Encryption** : chiffrement des sauvegardes avec clé externe