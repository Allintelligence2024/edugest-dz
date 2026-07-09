# 📋 EduGest DZ — Points manquants pour 100% complet
## Audit complet · 2 Juillet 2026 · Basé sur le repo réel (main, commit 06dbeb9)
## État actuel : 381 tests ✅ · 14 PRs mergées · ~88% complet

---

## LÉGENDE
- 🔴 **CRITIQUE** — bloque la mise en production ou casse une fonctionnalité annoncée
- 🟠 **IMPORTANT** — fonctionnalité incomplète visible par l'utilisateur
- 🟡 **UTILE** — améliore la qualité / l'expérience
- 🔵 **BONUS** — différenciateur concurrentiel, pas bloquant

---

## 1. BACKEND — Ce qui manque

### 1.1 🔴 Marketplace (mission en cours via DeepSeek)
- Tables `profils_marketplace`, `offres_cours`, `reservations`, `avis_marketplace`, `favoris_marketplace` — **pas encore créées**
- `MarketplaceController` avec endpoints publics + auth — **pas encore créé**
- `MarketplaceService` (recherche, score matching, réservation) — **pas encore créé**
- Routes publiques `/api/v1/marketplace/recherche`, `/featured`, `/centres/{id}` — **manquantes**
- 14 tests marketplace — **manquants**

### 1.2 🔴 Satim — Passage sandbox → production
- `config/satim.php` : `base_url` pointe encore sur `test.satim.dz`
- Il faut que le client signe un contrat avec **CIB/Algérie Poste** pour obtenir les credentials prod
- Le code est prêt — seul le `.env.production` doit être mis à jour avec les vraies clés
- **Action requise : toi (démarche administrative)**

### 1.3 🟠 Curriculum DZ — Lien avec les évaluations
- Le seeder SQL `seed_curriculum_algerie.sql` existe dans le repo
- Les tables `matieres`, `niveaux`, `branches_bac` sont créées
- **Manque :** lier `evaluations.matiere_id` → `matieres.id` (actuellement champ texte libre)
- **Manque :** valider que `eleve.niveau_scolaire` correspond à une branche du curriculum
- **Manque :** calcul moyen BEM/BAC par filière (pondération officielle ONEC)

### 1.4 🟠 Notifications push Firebase — Écran mobile parent
- `DeviceTokenController` existe
- `FirebaseService` existe avec `sendNotification()`
- **Manque :** envoi push automatique quand :
  - Réservation confirmée → parent notifié
  - Absence élève → parent notifié (SMS existe mais pas push)
  - Note publiée → élève/parent notifié
  - Bulletin généré → parent notifié
- **Manque :** test unitaire `FirebaseServiceTest`

### 1.5 🟠 Export iCal planning enseignant
- `PlanningController` existe avec export PDF
- **Manque :** endpoint `GET /api/v1/planning/ical` retournant un fichier `.ics`
- Standard utilisé par Google Calendar, Apple Calendar, Outlook
- Permet à l'enseignant de synchroniser son planning

### 1.6 🟠 Rapport PDF mensuel absences
- `AbsenceController` existe
- **Manque :** endpoint `GET /api/v1/absences/rapport-pdf?mois=7&annee=2026`
- Rapport récapitulatif par élève : nombre d'absences, justifiées/non-justifiées, tendance
- Utile pour les parents et pour les archives de l'établissement

### 1.7 🟠 Remplacement prof absent — automatique
- Si un enseignant est absent (pointage = absent), le planning a une séance sans prof
- **Manque :** logique de notification admin + suggestion de remplacement
- **Manque :** endpoint `POST /api/v1/seances/{id}/remplacer` avec `remplacant_id`

### 1.8 🟡 Scheduler — Vérification tâches planifiées
- `app/Console/Kernel.php` doit contenir les schedulers :
  - ✅ Facturation mensuelle transport+cantine (1er du mois à 6h)
  - **Manque :** SMS absent auto à 8h30 configuré en scheduler (actuellement déclenché manuellement ?)
  - **Manque :** Relances factures impayées J+1/J+3/J+7/J+15
  - **Manque :** Alertes entretien préventif (échéances dépassées)
  - **Manque :** Rapport stock bas (alertes seuil quotidiennes)

### 1.9 🟡 Super-Admin : gestion des tenants
- `SuperAdmin/` dossier existe
- **Manque :** endpoint `GET /api/v1/super-admin/tenants` — liste tous les centres
- **Manque :** endpoint `POST /api/v1/super-admin/tenants/{id}/suspendre`
- **Manque :** endpoint `GET /api/v1/super-admin/stats-globales` — KPIs plateforme
- **Manque :** endpoint `POST /api/v1/super-admin/marketplace/{tenantId}/verifier`

### 1.10 🟡 WhatsApp Webhook — Traitement entrant
- Route `POST /api/v1/whatsapp/webhook` existe
- **Manque :** traitement des messages entrants (parent répond "OUI" pour justifier absence)
- Actuellement le webhook enregistre le message mais ne déclenche aucune action

### 1.11 🟡 QR Code — Pointage élèves
- `PresenceController` existe
- **Manque :** endpoint `POST /api/v1/presences/qr-scan` (lecture QR code → marque présent)
- **Manque :** génération QR code par élève `GET /api/v1/eleves/{id}/qr-code`

### 1.12 🔵 Calcul BEM — Examen du brevet
- Matières + coefficients BEM existent dans le seeder
- **Manque :** endpoint `GET /api/v1/eleves/{id}/simulation-bem` 
- Calcule la moyenne prévisionnelle BEM sur la base des notes existantes

### 1.13 🔵 Calcul BAC par filière
- Branches BAC (Sciences, Lettres, Maths, etc.) existent dans le curriculum
- **Manque :** endpoint `GET /api/v1/eleves/{id}/simulation-bac`
- Pondération officielle ONEC par filière

---

## 2. FRONTEND REACT — Ce qui manque

### 2.1 🔴 MarketplacePage — Page unifiée
- `MarketplaceSearchPage.jsx` existe (depuis juin 28)
- `MarketplaceOffreDetailPage.jsx` existe
- `MarketplaceReservationPage.jsx` existe
- `MesReservationsPage.jsx` existe
- **Manque :** la nouvelle `MarketplacePage.jsx` de la mission en cours (design unifié avec recherche, featured, modal profil)
- **Manque :** lien dans Sidebar.jsx pointant vers marketplace

### 2.2 🟠 DashboardPage — Données réelles
- `DashboardPage.jsx` existe
- **Manque :** connexion API réelle (données probablement hardcodées ou partielles)
- KPIs à brancher : total élèves actifs, CA mois, absences du jour, séances en cours

### 2.3 🟠 PaiementCIBPage — Dashboard Satim
- `FacturesPage.jsx` existe
- **Manque :** page dédiée au suivi des paiements CIB/Dahabia
- Statuts : en_attente, confirmé, échoué, remboursé
- Historique + bouton remboursement

### 2.4 🟠 PointagePage — Interface badgeuse
- **Manque :** page React pour le pointage RFID/manuel enseignants
- Affiche : liste enseignants du jour, statut arrivée/départ, retards
- Permet saisie manuelle si badge défaillant

### 2.5 🟠 SuperAdminPage — Gestion globale
- **Manque :** page React super-admin
- Vue : tous les tenants, stats globales, vérification marketplace, suspension

### 2.6 🟡 ProfilePage — Paramètres utilisateur
- **Manque :** page profil utilisateur connecté
- Modifier mot de passe, activer 2FA, photo de profil, notifications préférées

### 2.7 🟡 NotificationsPage — Centre de notifications
- `MessagesPage.jsx` existe
- **Manque :** page dédiée aux notifications push reçues
- Historique des notifications, marquer comme lu

### 2.8 🟡 Pages existantes — Connecter les APIs
Les pages suivantes existent mais sont probablement avec des données mock :
- `GroupesPage.jsx` → brancher `GET /api/v1/groupes`
- `MatieresPage.jsx` → brancher `GET /api/v1/matieres`
- `EnseignantsListPage.jsx` → brancher `GET /api/v1/enseignants`
- `CampagnesPage.jsx` → brancher `GET /api/v1/campagnes`
- `AuditLogPage.jsx` → brancher `GET /api/v1/audit-logs`

### 2.9 🟡 Composants manquants — Réutilisables
- **Manque :** composant `<DataTable>` générique (pagination, tri, recherche)
- **Manque :** composant `<FormModal>` générique (create/edit dans modal)
- **Manque :** composant `<StatCard>` pour les dashboards
- **Manque :** composant `<ExportButton>` (PDF/Excel) réutilisable

### 2.10 🔵 Mode sombre / clair
- Actuellement : thème dark fixe
- **Manque :** toggle light/dark mode avec persistence localStorage

---

## 3. MOBILE React Native — Ce qui manque

### 3.1 🔴 Écrans parent — Paiement CIB mobile
- 8 écrans parent existent (Dashboard, Élèves, Notes, Absences, Planning, Messages, Factures, Profil)
- **Manque :** intégration WebView Satim pour paiement CIB depuis mobile
- Flux : `FacturesScreen` → bouton "Payer CIB" → WebView → redirect → confirmation

### 3.2 🟠 Notifications push — Configuration Expo
- Firebase FCM configuré côté backend
- **Manque :** configuration `app.json` Expo avec `google-services.json` (Android) et `GoogleService-Info.plist` (iOS)
- **Manque :** `expo-notifications` configuré dans `App.js`
- **Manque :** handler de notification pour rediriger vers le bon écran

### 3.3 🟠 Écran parent — Réservations marketplace
- **Manque :** `MarketplaceScreen.jsx` dans le mobile parent
- Parent cherche un centre, voit le profil, réserve depuis le mobile
- Flux : recherche → profil → réserver → confirmation

### 3.4 🟠 Écran enseignant — Saisie notes depuis mobile
- `NotesScreen.jsx` existe dans `screens/enseignant/`
- **Manque :** formulaire de saisie note (actuellement probablement liste read-only)
- Enseignant doit pouvoir saisir une note directement depuis son téléphone

### 3.5 🟡 Écran admin — Pointage enseignants
- `AdminDashboardScreen.jsx` existe
- **Manque :** `AdminPointageScreen.jsx` — voir qui est arrivé, qui est absent ce matin
- Vue temps réel : présent ✅ / absent ❌ / en retard ⚠️

### 3.6 🟡 Biométrie — Authentification
- **Manque :** `expo-local-authentication` pour login par empreinte/Face ID
- Alternative au mot de passe pour les utilisateurs fréquents (enseignants, admin)

### 3.7 🟡 Mode hors-ligne — Cache local
- **Manque :** `@react-native-async-storage/async-storage` pour cacher les données critiques
- Si pas de réseau : planning, liste élèves, dernières notes toujours accessibles

### 3.8 🔵 App Store / Play Store — Publication
- **Manque :** `eas.json` pour EAS Build (Expo Application Services)
- **Manque :** icône app + splash screen personnalisés EduGest DZ
- **Manque :** configuration store (description, screenshots, catégorie "Éducation")

---

## 4. INFRASTRUCTURE / DEVOPS — Ce qui manque

### 4.1 🔴 VPS Production — Non configuré
- `docker-compose.prod.yml` ✅ prêt
- `deploy.sh` ✅ prêt
- `server-setup.sh` ✅ prêt
- **Manque :** un VPS réel (OVH, Hetzner, ou serveur local)
- **Action requise : toi** — louer un VPS et lancer `./server-setup.sh`

### 4.2 🔴 Domaine + SSL — Non configuré
- Nginx config prod avec SSL ✅ prête
- **Manque :** domaine `app.edugest.dz` acheté et pointé sur le VPS
- Certbot configurera le SSL automatiquement une fois le domaine actif

### 4.3 🟠 Branch protection sur main — Non configuré
- **Manque :** Settings → Branches → Add ruleset
- Require PR + CI check "CI — EduGest DZ / backend (pull request)"
- **Action requise : toi (5 minutes)**

### 4.4 🟠 Variables d'environnement — Secrets GitHub
- Pour le CD (deploy.yml) fonctionner, il faut configurer dans GitHub :
  - `Settings → Secrets → Actions`
  - `SSH_PRIVATE_KEY` — clé SSH du VPS
  - `SSH_HOST` — IP du VPS
  - `SSH_USERNAME` — user du VPS (ex: ubuntu)
- **Action requise : toi (après avoir le VPS)**

### 4.5 🟠 Monitoring production — Manquant
- **Manque :** Sentry pour tracking des erreurs en production
- **Manque :** Laravel Telescope en staging (déjà dans composer ?)
- **Manque :** alertes si CI échoue → notification email/Slack

### 4.6 🟡 Backup PostgreSQL — Manquant
- **Manque :** script de backup automatique quotidien
- Ajouter dans `docker-compose.prod.yml` : service `backup` avec `pg_dump` + upload S3/Backblaze
- **Données critiques** : élèves, factures, paiements — perte = catastrophe

### 4.7 🟡 Rate limiting API — Renforcé
- Laravel throttle existe
- **Manque :** rate limiting spécifique :
  - Auth : 5 tentatives / 15 min
  - API générale : 100 req / min par tenant
  - Webhook WhatsApp : liste blanche IP Twilio

---

## 5. QUALITÉ / TESTS — Ce qui manque

### 5.1 🟠 Tests d'intégration Satim
- **Manque :** `SatimGatewayTest` avec mock HTTP (Guzzle MockHandler)
- Tester : initiation, confirmation, échec, remboursement
- Actuellement non testé (sandbox seulement)

### 5.2 🟠 Tests E2E — Non configurés
- **Manque :** Playwright ou Cypress pour tester les flux complets frontend
- Flux critique à tester : login → créer élève → générer facture → payer → bulletin

### 5.3 🟡 Tests performance — Aucun
- **Manque :** benchmark avec `artillery` ou `k6`
- Vérifier que l'API tient 100 req/sec avec 500 élèves par tenant

### 5.4 🟡 Tests sécurité — Aucun
- **Manque :** scan OWASP avec ZAP ou Burp Suite
- Points à vérifier : injection SQL, XSS, CSRF, tenant isolation

---

## 6. DOCUMENTATION — Ce qui manque

### 6.1 🟠 README — Mise à jour
- README existe mais probablement pas à jour avec tous les modules
- **Manque :** section "Modules disponibles" avec liste complète
- **Manque :** section "Variables d'environnement" complète
- **Manque :** section "Architecture" avec lien vers le diagramme HTML

### 6.2 🟡 Guide déploiement VPS
- **Manque :** `DEPLOYMENT.md` step-by-step pour un développeur qui reprend le projet
- Étapes : louer VPS → `./server-setup.sh` → configurer `.env.production` → lancer → vérifier

### 6.3 🟡 Guide API — Pour les intégrateurs
- Swagger UI existe (`/api/documentation`)
- **Manque :** collection Postman exportée avec exemples réels
- **Manque :** guide "Premiers pas API" avec exemple d'authentification + premier appel

---

## 7. RÉSUMÉ PAR PRIORITÉ

### 🔴 CRITIQUES (bloquant prod) — 4 points
| # | Point | Qui | Durée |
|---|---|---|---|
| 1 | Marketplace mission (DeepSeek) | DeepSeek | En cours |
| 2 | VPS + Domaine + SSL | Toi | Variable |
| 3 | Satim credentials prod | Toi (CIB/AP) | Démarche admin |
| 4 | Secrets GitHub CI/CD | Toi (après VPS) | 10 min |

### 🟠 IMPORTANTS (fonctionnalités manquantes visibles) — 12 points
| # | Point | Qui | Effort DeepSeek |
|---|---|---|---|
| 5 | Push Firebase mobile (parent notifié) | DeepSeek | 1 mission |
| 6 | Scheduler tâches auto (SMS 8h30, relances) | DeepSeek | 1 mission |
| 7 | Paiement CIB mobile (WebView Satim) | DeepSeek | 1 mission |
| 8 | Notifications push Expo config | DeepSeek | inclus mobile |
| 9 | Page PointagePage React | DeepSeek | inclus finitions |
| 10 | Page SuperAdminPage React | DeepSeek | inclus finitions |
| 11 | Page PaiementCIBPage React | DeepSeek | inclus finitions |
| 12 | DashboardPage — données réelles | DeepSeek | inclus finitions |
| 13 | Branch protection GitHub | Toi | 5 min |
| 14 | Export iCal planning enseignant | DeepSeek | 1 mission |
| 15 | Rapport PDF absences mensuel | DeepSeek | inclus rapports |
| 16 | Remplacement prof absent | DeepSeek | inclus planning |

### 🟡 UTILES (qualité + expérience) — 11 points
| # | Point | Qui | Effort |
|---|---|---|---|
| 17 | Super-admin endpoints manquants | DeepSeek | inclus super-admin |
| 18 | WhatsApp webhook traitement entrant | DeepSeek | inclus comm |
| 19 | QR code pointage élèves | DeepSeek | inclus pointage |
| 20 | Composants React réutilisables | DeepSeek | inclus finitions |
| 21 | ProfilePage + NotificationsPage | DeepSeek | inclus finitions |
| 22 | Backup PostgreSQL automatique | DeepSeek | 1 mission infra |
| 23 | Rate limiting renforcé | DeepSeek | inclus sécurité |
| 24 | Monitoring Sentry + alertes | DeepSeek | 1 mission |
| 25 | README mis à jour | DeepSeek | inclus |
| 26 | Guide déploiement VPS | DeepSeek | inclus |
| 27 | Biométrie mobile | DeepSeek | inclus mobile |

### 🔵 BONUS (différenciateurs) — 4 points
| # | Point | Effort |
|---|---|---|
| 28 | Calcul BEM simulation | 1 mission |
| 29 | Calcul BAC par filière | 1 mission |
| 30 | Mode hors-ligne mobile | 1 mission |
| 31 | Publication App Store / Play Store | Démarche |

---

## 8. MISSIONS DEEPSEEK RESTANTES (dans l'ordre)

Après que la mission Marketplace soit mergée, voici l'ordre optimal :

```
MISSION 1 : MISSION_MARKETPLACE.md          ← En cours
MISSION 2 : MISSION_FINITIONS_FRONTEND.md   ← À créer (points 9,10,11,12,20,21,25,26)
MISSION 3 : MISSION_SCHEDULERS_AUTO.md      ← À créer (point 6)
MISSION 4 : MISSION_MOBILE_COMPLET.md       ← À créer (points 7,8,27,35)
MISSION 5 : MISSION_NOTIFICATIONS_PUSH.md   ← À créer (point 5)
MISSION 6 : MISSION_RAPPORTS_PDF.md         ← À créer (points 15, 12 BEM/BAC)
MISSION 7 : MISSION_SECURITE_INFRA.md       ← À créer (points 22,23,24)
```

---

## 9. CE QUE TU DOIS FAIRE TOI (sans DeepSeek)

```
☐ 1. Branch protection GitHub — 5 minutes
     Settings → Branches → Add ruleset → protect main

☐ 2. Louer un VPS — Variable (OVH 5€/mois, Hetzner 4€/mois)
     Recommandé : Ubuntu 22.04, 2 vCPU, 4GB RAM, 40GB SSD

☐ 3. Acheter le domaine app.edugest.dz (ou edugest.dz)
     NIC.dz — domaine .dz ≈ 1500-3000 DA/an

☐ 4. Lancer ./server-setup.sh sur le VPS
     Configure Docker, Nginx, Certbot automatiquement

☐ 5. Configurer les secrets GitHub (SSH_PRIVATE_KEY, SSH_HOST, SSH_USERNAME)
     Settings → Secrets → Actions

☐ 6. Contacter CIB / Algérie Poste pour credentials Satim production
     Nécessite : RIB entreprise, registre de commerce, contrat Satim

☐ 7. Trouver 1 client pilote réel (centre ou école privée à Oran)
     Feedback réel > perfectionnisme technique
```

---

## 10. SCORE PAR DOMAINE (état actuel honnête)

| Domaine | Score | Manque principal |
|---|---|---|
| Backend API | **92%** | Marketplace + schedulers + super-admin complet |
| Frontend React | **75%** | Dashboard réel + 4 pages manquantes + APIs branchées |
| Mobile React Native | **65%** | Paiement CIB + push + marketplace + hors-ligne |
| Infrastructure | **70%** | VPS réel + domaine + secrets + backup |
| Tests | **80%** | E2E + Satim mock + sécurité |
| Documentation | **60%** | README complet + guide déploiement + Postman |
| **GLOBAL** | **~80%** | 7 missions DeepSeek + actions toi |
