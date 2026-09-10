# SATIM Production Checklist — EduGest DZ

## Vue d'ensemble

SATIM (Société Algérienne de Interbancaires de Mobilité) est le système de paiement
électronique algérien. Ce document liste les démarches administratives et techniques
pour passer du mode SANDBOX (test) au mode PRODUCTION.

---

## Phase 1 — Démarches Administratives

### 1.1 Dossier d'enregistrement

- [ ] Créer un compte sur le portail SATIM : https://merchant.satim.dz
- [ ] Remplir le formulaire d'enregistrement commerçant
- [ ] Fournir les documents requis :
  - [ ] Registre de commerce (RC) en cours de validité
  - [ ] Numéro d'identification fiscale (NIF)
  - [ ] Attestation d'imposition
  - [ ] Pièce d'identité du gérant
  - [ ] Relevé bancaire du compte associé
- [ ] Signer le contrat de prestations de services avec SATIM
- [ ] Attendre la validation (délai : 5-10 jours ouvrables)

### 1.2 Configuration terminal

- [ ] Réceptionner les identifiants terminal par email SATIM :
  - `SATIM_TERMINAL_ID` — Identifiant du terminal
  - `SATIM_MERCHANT_ID` — Identifiant commerçant
  - `SATIM_PASSWORD` — Mot de passe terminal
- [ ] Tester en mode SANDBOX avec les identifiants de test
- [ ] Valider les transactions test avec SATIM (commission 1.5-3%)

### 1.3 Validation bancaire

- [ ] Associer le compte bancaire de l'établissement
- [ ] Fournir l'attestation bancaire
- [ ] Tester les virements de test
- [ ] Obtenir la validation bancaire finale

---

## Phase 2 — Variables d'environnement Railway

### 2.1 Variables obligatoires (Secrets Railway)

| Variable | Description | Valeur SANDBOX | Valeur PRODUCTION |
|---|---|---|---|
| `SATIM_TERMINAL_ID` | ID terminal | Valeur de test | Valeur attribuée |
| `SATIM_MERCHANT_ID` | ID commerçant | Valeur de test | Valeur attribuée |
| `SATIM_PASSWORD` | Mot de passe | Mot de passe test | Mot de passe prod |
| `SATIM_URL` | URL API | `https://test.satim.dz/payment/rest` | `https://satim.dz/payment/rest` |
| `SATIM_SANDBOX` | Mode test | `true` | `false` |

### 2.2 Configuration Railway

```
# Dans Railway → Variables du service
SATIM_TERMINAL_ID=xxxxxxxx
SATIM_MERCHANT_ID=xxxxxxxx
SATIM_PASSWORD=xxxxxxxx
SATIM_URL=https://satim.dz/payment/rest
SATIM_SANDBOX=false
```

> ⚠️ Toutes ces variables doivent être dans les **Secrets** Railway (pas les variables publiques).

---

## Phase 3 — Validation technique

### 3.1 Tests de paiement

- [ ] Initier un paiement test via l'API SATIM
- [ ] Vérifier la redirection vers la page de paiement SATIM
- [ ] Simuler un paiement réussi (carte de test)
- [ ] Vérifier le callback de retour (success)
- [ ] Simuler un paiement échoué
- [ ] Vérifier le callback de retour (failure)
- [ ] Tester le timeout de session

### 3.2 Intégration EduGest

- [ ] Vérifier que `config/satim.php` lit correctement les variables d'env
- [ ] Tester la création d'une facture avec paiement SATIM
- [ ] Vérifier la mise à jour du statut facture après paiement
- [ ] Tester l'historique des transactions
- [ ] Vérifier les logs d'audit SATIM

### 3.3 Sécurité

- [ ] Vérifier HTTPS sur toutes les URLs SATIM
- [ ] Valider que les clés ne sont pas dans le code source
- [ ] Tester la protection CSRF sur les callbacks
- [ ] Vérifier les headers de sécurité (HSTS, CSP)

---

## Phase 4 — Mise en production

### 4.1 Pré-déploiement

- [ ] `SATIM_SANDBOX=false` dans les secrets Railway
- [ ] `SATIM_URL=https://satim.dz/payment/rest` dans les secrets Railway
- [ ] Dernier test en SANDBOX validé
- [ ] Backup de la base de données

### 4.2 Déploiement

- [ ] Push sur `main` → déploiement Railway automatique
- [ ] Vérifier les logs de déploiement
- [ ] Tester un paiement réel (montant minimum : 100 DZD)
- [ ] Vérifier le réception du virement sur le compte bancaire

### 4.3 Post-déploiement

- [ ] Monitorer les transactions pendant 48h
- [ ] Vérifier les erreurs dans Sentry
- [ ] Valider les réconciliations bancaires
- [ ] Documenter les numéros de transaction

---

## Contact SATIM

- **Support technique** : support@satim.dz
- **Support commercial** : commercial@satim.dz
- **Documentation API** : https://docs.satim.dz
- **Portail marchand** : https://merchant.satim.dz

---

*Dernière mise à jour : 2026-07-16*
*Prochaine révision : Après validation SATIM production*
