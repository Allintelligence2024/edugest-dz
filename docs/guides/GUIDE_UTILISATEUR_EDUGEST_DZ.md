# 📘 Guide Utilisateur — EduGest DZ
## Manuel complet · 4 Juillet 2026 · Version 1.0

---

# PARTIE 1 — TOI (Créateur / Super-Admin)

## Accès
- **URL :** `https://app.edugest.dz`
- **Email :** ton email super-admin
- **Rôle :** `super_admin` — accès total à TOUT

## Ce que tu fais au quotidien

### 1. Onboarder un nouveau centre
```
1. Créer le tenant dans la BDD (ou via endpoint super-admin)
2. Créer le compte admin du directeur
3. Lui envoyer ses credentials par email
4. Il configure son établissement lui-même
```

### 2. Surveiller la plateforme
```
Dashboard super-admin → /super-admin
- Voir tous les centres actifs
- CA global de la plateforme
- Nombre d'élèves total
- Santé du système
```

### 3. Health check système
```
GET https://app.edugest.dz/api/health
→ Vérifie PostgreSQL, Redis, Storage
→ À surveiller avec un outil comme UptimeRobot
```

### 4. Vérifier un profil Marketplace
```
SurveillancePage → Marketplace → Profils en attente
→ Cliquer "Vérifier" → le centre obtient le badge ✅
→ Apparaît en priorité dans la recherche
```

### 5. Suspendre un centre problématique
```
SuperAdminPage → Tenants → [Nom centre] → Suspendre
→ Tous les users du tenant perdent l'accès immédiatement
```

### 6. Consulter les logs d'audit
```
AuditLogPage → Filtrer par tenant + date
→ Toutes les actions enregistrées (qui a fait quoi, quand)
```

### 7. Monitoring backup PostgreSQL
```
docker logs edugest_backup
→ Backup quotidien automatique dans ./backups/
→ Garder 7 jours / 4 semaines / 6 mois
```

### 8. Déployer une mise à jour
```
git push origin main
→ GitHub Actions CI → tests
→ CD automatique SSH → serveur prod
→ Zero-downtime (Docker rolling update)
```

---

# PARTIE 2 — DIRECTEUR (Admin de l'établissement)

## Première connexion — Configuration initiale (30 min)

### Étape 1 — Paramètres de l'établissement
```
Profil → Paramètres établissement
- Nom, adresse, wilaya, téléphone, logo
- Horaires ouverture/fermeture
- Compte bancaire (pour les paiements)
```

### Étape 2 — Créer les enseignants
```
Menu → Enseignants → + Ajouter
- Nom, prénom, email, téléphone
- Spécialité(s) : Maths, Physique, Arabe...
- Salaire base → le système calcule IRG/CNAS automatiquement
→ L'enseignant reçoit ses credentials par email
```

### Étape 3 — Créer les groupes/classes
```
Menu → Groupes → + Créer groupe
- Nom : "3AS Sciences - Groupe A"
- Matière principale
- Niveau : 3AS, 2AS, 3AM...
- Enseignant assigné
- Capacité max
```

### Étape 4 — Inscrire les élèves
```
Menu → Élèves → + Nouvel élève
- Informations élève (nom, prénom, date naissance, niveau)
- Informations parent (nom, téléphone × 2, email)
- Assigner au groupe
→ Import CSV possible si tu as déjà une liste
```

### Étape 5 — Créer le planning
```
Menu → Planning → + Séance
- Groupe, matière, enseignant, salle
- Jour + heure de début/fin
→ Le système détecte automatiquement les conflits
```

### Étape 6 — Configurer les tarifs
```
Menu → Finances → Paramètres tarifs
- Tarif mensuel scolarité
- Tarif transport (si applicable)
- Tarif cantine (si applicable)
→ Les factures seront générées automatiquement le 1er de chaque mois
```

---

## Utilisation quotidienne

### Matin (8h00 - 8h30)
```
1. Dashboard → voir les absences du jour (actualisées automatiquement)
2. Pointage → marquer les enseignants présents/absents
3. Si enseignant absent → contacter un remplaçant
4. Les SMS parents sont envoyés automatiquement à 8h30
```

### Dans la journée
```
- Émettre des billets (entrée tardive, sortie anticipée, convocation)
  Menu → Billets → + Émettre billet
  
- Traiter les alertes de surveillance
  Menu → Surveillance → Alertes non traitées
  
- Voir les notifications en temps réel (badge rouge dans le menu)
```

### Fin de mois
```
1. Finances → Vérifier les factures générées automatiquement
2. Relances automatiques envoyées (J+1, J+3, J+7, J+15)
3. Paie personnel → Générer les fiches de paie
   Menu → Personnel → [Nom] → Générer paie → IRG/CNAS calculés auto
4. Budget → Saisir les dépenses du mois
5. Rapport → Exporter le bilan PDF
```

### Module Transport
```
Configuration (1 fois) :
  Menu → Transport → Circuits → + Créer circuit
  - Nom circuit, capacité bus, chauffeur, horaires
  - Ajouter les arrêts + heures
  - Inscrire les élèves au circuit

Usage quotidien :
  Transport → Pointage bus → Matin/Soir
  → Marquer présent/absent sur le bus
  → SMS parent si enfant absent du bus
```

### Module Cantine
```
Configuration :
  Cantine → Menus → Créer menu semaine
  Cantine → Inscriptions → Inscrire élèves (régimes/allergies)

Quotidien :
  Cantine → Pointage → Valider les présences repas
  → Comptage automatique → Stock cuisine mis à jour
  → Facturation mensuelle automatique
```

### Module Stock
```
Stock → Articles → + Ajouter article
  - Nom, référence, catégorie, prix unitaire
  - Seuil d'alerte (notification automatique si stock bas)

Stock → Mouvements → Entrée/Sortie
  → Le stock est mis à jour automatiquement
  
Stock → Rapport → Inventaire PDF complet
```

### Module Entretien
```
Entretien → Locaux → Créer les locaux (salles, bureaux...)
Entretien → Interventions → Signaler une panne
  - Local, description, priorité (basse/normale/haute/urgente)
  - Assigner à un prestataire
  - Coût → ajouté automatiquement au Budget (M13)

Entretien → Préventif → Planifier entretiens réguliers
  → Alertes automatiques avant l'échéance (chaque lundi)
```

### Module Surveillance Dahua
```
Surveillance → Caméras → + Ajouter caméra
  - Nom, numéro de série DVR, type, localisation
  - Horaires normaux (ex: 7h-20h)
  → EduGest te donne l'URL webhook à configurer sur le DVR

Sur le DVR Dahua :
  Paramètres → Réseau → Événements HTTP
  URL : https://app.edugest.dz/api/v1/surveillance/webhook
  Méthode : POST · Format : JSON
  Événements : mouvement, intrusion, perte signal

Quand une alerte arrive :
  → SMS immédiat si critique (hors horaires)
  → Notification push sur l'app admin
  → Alerte visible dans Surveillance → Alertes
  → Cliquer "Traiter" + ajouter une note
```

### Rapports & Analyses
```
Menu → Rapports → Absences PDF
  → Rapport mensuel : tous les élèves avec absences, justifiées/non

Menu → Élèves → [Élève] → Simulation BEM
  → Calcul prévisionnelle BEM basé sur les notes actuelles
  → Coefficients officiels MEN algérien

Menu → Élèves → [Élève] → Simulation BAC
  → Choisir la filière (Sciences, Maths, Lettres, Gestion...)
  → Mention prévisionnelle selon barème ONEC
```

---

# PARTIE 3 — ENSEIGNANT

## Première connexion
```
1. Recevoir email avec credentials
2. Se connecter sur app.edugest.dz
   OU télécharger l'app mobile (Android/iOS)
3. Changer son mot de passe
4. Optionnel : activer 2FA (Profil → Sécurité → Activer 2FA)
```

## Usage quotidien

### Faire l'appel (présences)
```
WEB :
  Planning → Cliquer sur la séance → Faire l'appel
  → Pour chaque élève : Présent / Absent / Retard / Excusé
  → Valider → notifications automatiques aux parents absents

MOBILE (app enseignant) :
  Onglet "Présences" → Sélectionner séance → Appel rapide
  → Scanner QR code élève pour pointer (plus rapide)
```

### Saisir les notes
```
WEB :
  Évaluations → + Créer évaluation
  - Type : Devoir / Interrogation / Examen
  - Coefficient, date, matière, groupe
  → Notes → Saisir note par élève

MOBILE :
  Onglet "Notes" → Sélectionner évaluation → Saisir
```

### Générer les bulletins
```
Bulletins → Sélectionner groupe + trimestre + année scolaire
→ Cliquer "Générer tous les bulletins"
→ Calcul automatique des moyennes + rang + appréciation
→ PDF disponible pour chaque élève
→ Notification push aux parents : "Bulletin disponible"
```

### Consulter son planning
```
WEB : Menu → Planning → Vue semaine/mois
MOBILE : Onglet "Planning"
→ Export iCal → Synchroniser avec Google Calendar / Apple Calendar
```

### Exporter son planning vers Google Calendar
```
Planning → Exporter iCal → Télécharger le fichier .ics
→ Google Calendar → Importer → Sélectionner le fichier
→ Toutes tes séances apparaissent dans Google Calendar
```

---

# PARTIE 4 — PARENT

## Télécharger l'app mobile
```
Android : Play Store → "EduGest DZ"
iOS     : App Store → "EduGest DZ"
→ Se connecter avec les credentials reçus de l'établissement
```

## Recevoir les notifications
```
Autoriser les notifications push lors de la première ouverture
→ Tu recevras automatiquement :
  ✅ Si ton enfant est absent (8h30 le matin)
  📝 Quand une note est publiée
  📄 Quand le bulletin est disponible
  💳 Confirmation de paiement
  📅 Réservation confirmée (marketplace)
```

## Justifier une absence via WhatsApp
```
Tu reçois un SMS : "Votre enfant Mohamed est absent ce jour."
→ Répondre "OUI" par WhatsApp au numéro de l'établissement
→ L'absence est automatiquement justifiée dans le système
→ Tu reçois une confirmation
```

## Payer une facture (mobile)
```
App → Onglet "Factures"
→ Voir toutes les factures (payées / impayées / en retard)
→ Sur une facture impayée : "Payer par CIB / Dahabia"
→ Page sécurisée Satim s'ouvre (comme un achat en ligne)
→ Saisir les 16 chiffres de ta carte CIB ou Dahabia
→ Code SMS de confirmation → Valider
→ Paiement confirmé + SMS de confirmation
```

## Consulter les notes et bulletins
```
App → Onglet "Notes"
→ Voir toutes les notes par matière + moyennes
→ Voir le rang dans le groupe

App → Onglet "Bulletins"
→ Télécharger le bulletin PDF
→ Voir la moyenne générale + appréciation
```

## Trouver un autre centre (Marketplace)
```
App → Onglet "Centres"
→ Filtrer par wilaya, matière, niveau
→ Voir les profils avec avis + notes
→ Centres avec badge ✅ = vérifiés par EduGest
→ "Réserver une séance" → essai gratuit si disponible
→ Attendre la confirmation du centre (SMS + push)
```

## Laisser un avis
```
App → Mes Réservations → [Réservation terminée]
→ "Laisser un avis" → Note 1 à 5 étoiles + commentaire
→ L'avis apparaît sur le profil du centre
```

---

# PARTIE 5 — ÉLÈVE

## Accès
```
WEB : app.edugest.dz → se connecter
APP : télécharger EduGest DZ → se connecter
```

## Consulter son planning
```
Menu → Planning
→ Voir toutes les séances de la semaine
→ Salles, enseignants, horaires
```

## Voir ses notes et simuler BEM/BAC
```
Menu → Notes
→ Notes par matière + coefficients + moyennes
→ Évolution sur le trimestre

Menu → Simulation BEM (si en 4AM)
→ Moyenne prévisionnelle selon les notes actuelles
→ Mention estimée

Menu → Simulation BAC (si en 3AS)
→ Choisir sa filière
→ Voir sa moyenne pondérée ONEC
→ Mention estimée
```

## QR Code personnel
```
Menu → Mon QR Code
→ Afficher le QR code à l'entrée de la séance
→ L'enseignant scanne → présence enregistrée automatiquement
→ Plus besoin d'appel manuel
```

---

# PARTIE 6 — PERSONNEL NON-ENSEIGNANT
## (Surveillant, femme de ménage, comptable, secrétaire)

## Pointer son arrivée
```
WEB : Se connecter → Pointage → Pointer arrivée
APP admin : Onglet Pointage → Son nom → Arrivée

OU via badge RFID (si lecteur installé) :
→ Passer le badge → pointage automatique
```

## Voir sa fiche de paie
```
Menu → Ma Paie
→ Voir le bulletin mensuel : brut, CNAS, IRG, net
→ Télécharger le bulletin PDF
```

## Demander un congé
```
Menu → Congés → + Nouvelle demande
- Type : annuel / maladie / familial
- Dates début/fin
- Motif (optionnel)
→ Le directeur reçoit une notification
→ Tu reçois la réponse (accepté/refusé) par notification
```

## Signaler une panne
```
Menu → Signalement → + Nouveau
- Local concerné (ex: "Salle 3 - climatiseur")
- Description du problème
- Priorité estimée
→ Le directeur crée un ticket d'intervention
```

---

# PARTIE 7 — API POUR LES DÉVELOPPEURS

## Documentation Swagger
```
https://app.edugest.dz/api/documentation
→ Interface interactive complète
→ Tous les endpoints documentés
→ Tester en live avec son token JWT
```

## Authentification
```bash
# 1. Obtenir un token
POST /api/v1/auth/login
{
  "email": "admin@ecole.dz",
  "password": "motdepasse"
}
→ Retourne : { "token": "eyJ0eXAi..." }

# 2. Utiliser le token
Authorization: Bearer eyJ0eXAi...
X-Tenant-ID: uuid-du-tenant

# 3. Rafraîchir le token
POST /api/v1/auth/refresh
```

## Health Check (monitoring)
```bash
GET https://app.edugest.dz/api/health
→ Status 200 = tout va bien
→ Status 503 = un service est dégradé
```

---

# RÉSUMÉ RAPIDE — Qui fait quoi

| Action | Super-Admin | Directeur | Enseignant | Parent | Élève | Personnel |
|---|---|---|---|---|---|---|
| Voir tous les centres | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gérer les élèves | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Faire l'appel | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Saisir les notes | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Voir ses notes | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Payer une facture | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Générer la paie | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Voir sa fiche paie | ❌ | ✅ | ✅ | ❌ | ❌ | ✅ |
| Gérer le budget | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Alertes surveillance | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Marketplace (chercher) | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ |
| Marketplace (profil) | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Simulation BEM/BAC | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Health check système | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
