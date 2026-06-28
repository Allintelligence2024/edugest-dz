# Marketplace — Phase 1

## Architecture

### Nouvelles tables
- `offres_publiques` — offres visibles sur le marketplace
- `reservations` — réservations d'offres par les parents/élèves
- `avis` — avis laissés après une réservation terminée

### Endpoints
- **Publiques** (no auth) : recherche d'offres, détail offre, avis par enseignant
- **Protégées** (JWT) : CRUD offres (enseignant/admin), réservations, paiement, avis

## Parcours utilisateur
1. **Recherche** → filtres (wilaya, matière, niveau, tarif, type cours)
2. **Fiche détail** → infos enseignant, avis, tarifs
3. **Réservation** → date, message, confirmation
4. **Paiement** → redirection Satim (CIB/Dahabia) ou BaridiMob
5. **Séance** → lien Jitsi généré côté serveur pour cours en ligne

## Modèles

### OffrePublique
- `type_offre` : enseignant | centre
- `tarif_seance`, `tarif_mensuel`
- `type_cours` : presentiel | en_ligne | les_deux
- `wilaya_id` référence vers wilayas (FK int)
- `capacite_max`, `places_restantes`
- `statut` : active | inactive | archivee

### Reservation
- `statut` : en_attente | confirmee | payee | annulee | terminee
- `montant`, `commission`
- `mode_paiement`, `paiement_en_ligne_id`
- `lien_visio` : généré après paiement si cours en ligne

### Avis
- `note` : 1-5
- `commentaire` : optionnel
- Lié à une réservation terminée

## Commission
- Configurable par tenant via `plan_abonnement`
- Plan gratuit : 10 %, pro : 7 %, premium : 5 %
- Valeur par défaut : 7 %

## Visioconférence
- Génération d'URL Jitsi Meet côté serveur
- Format : `https://meet.jit.si/EduGestDZ_{reservationId}_{random}`
- Pas d'intégration SDK native en phase 1

## Incréments futurs
- Chat temps réel entre parent et enseignant
- Avis avancés (photos, réponses)
- SDK visio natif (Jitsi Meet SDK mobile/desktop)
- Abonnements récurrents
- Paiement à la séance
