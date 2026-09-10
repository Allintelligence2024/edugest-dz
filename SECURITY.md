# Politique de sécurité — EduGest DZ

## Versions prises en charge

| Version | Prise en charge |
|---|---|
| `main` (SaaS hébergé, dernière image) | ✅ Oui |
| Versions self-hosted ≤ 6 mois | ✅ Correctifs de sécurité |
| Versions self-hosted > 6 mois | ❌ Mettre à jour d'abord |

## Signaler une vulnérabilité

**Ne jamais ouvrir un ticket public, une PR ou une discussion pour une
faille de sécurité.** Privilégier, dans l'ordre :

1. Le signalement privé GitHub (*Security → Report a vulnerability*) sur
   ce dépôt — canal préféré, avec suivi.
2. À défaut, un courriel à l'adresse de contact du dépôt, objet
   `[SECURITE]`, en décrivant : version concernée, étapes de reproduction,
   impact estimé, et si possible un correctif ou une atténuation.

Délais indicatifs : accusé de réception sous 72 h ouvrées, qualification
sous 7 jours, correctif selon criticité (critique : sous 7 jours ;
haute : sous 30 jours). La divulgation publique est coordonnée avec le
rapporteur après déploiement du correctif — pas avant.

## Périmètre

Sont dans le périmètre : l'API (`backend/`), le frontend (`frontend/`),
l'application mobile (`mobile/`), les configurations de déploiement et les
workflows CI du dépôt. Sont hors périmètre : les dépendances tierces (les
rapporter à leurs mainteneurs — `composer audit` et `npm audit` tournent
en CI), les installations modifiées par des tiers, et le social engineering.

## Mesures en place (résumé)

L'architecture de sécurité est documentée dans `docs/SECURITE.md` :
isolation multi-tenant (scopes + RLS PostgreSQL), RBAC route + policy,
jetons JWT avec rotation et liste noire, audit immuable chaîné (HMAC),
chiffrement des colonnes sensibles, MFA administrateurs, honeypots,
rate limiting et en-têtes OWASP. La CI bloque sur `composer audit`,
`npm audit --audit-level=high` et Gitleaks.

## Conformité

Traitement des données personnelles selon la loi algérienne 18-07 :
voir `docs/ANPDP_DECLARATION.md` et le registre des traitements
(`docs/REGISTRE_TRAITEMENTS.md`, Sprint 6).
