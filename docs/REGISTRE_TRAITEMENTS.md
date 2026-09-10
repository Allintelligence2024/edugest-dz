# Registre des activités de traitement — EduGest DZ (loi 18-07)

## À quoi sert ce document

La loi n° 18-07 (protection des données à caractère personnel) impose au
**responsable du traitement** de tenir un registre des activités de
traitement. Dans le modèle EduGest DZ :

- **Responsable du traitement** = l'établissement scolaire (le client) ;
- **Sous-traitant** = EduGest DZ (la plateforme), qui agit sur instruction
  du responsable.

Ce document est le **modèle pré-rempli fourni par le sous-traitant** :
chaque établissement doit le compléter (identité, durées locales,
destinataires propres) et le conserver à disposition de l'ANPDP. La
déclaration préalable à l'ANPDP reste à la charge du responsable — voir
[`ANPDP_DECLARATION.md`](ANPDP_DECLARATION.md).

---

## Traitements

| # | Traitement | Finalité | Base légale (18-07) | Personnes concernées | Données | Rétention | Application logicielle |
|---|-----------|----------|--------------------|--------------------|---------|-----------|----------------------|
| 1 | Gestion de la scolarité | Inscriptions, dossiers élèves, notes, bulletins, présences, absences | Mission d'intérêt public (service d'enseignement) + contrat de scolarité | Élèves (mineurs), parents | Identité, coordonnées parents, parcours scolaire | Durée de scolarité + 5 ans (archives) | Export d'archive annuelle (`/rgpd/archiver-annee`) |
| 2 | Finance & facturation | Factures, paiements CIB/Dahabia, relances, échéanciers | Exécution du contrat + obligations comptables | Élèves/parents, établissement | Identité, montants, moyens de paiement (tronqués) | 10 ans (obligation comptable) | — |
| 3 | Paie & personnel | Salaires, contrats, pointage, congés (IRG/CNAS) | Obligation légale + contrat de travail | Enseignants, personnel | Identité, données bancaires (chiffrées), salaires | 10 ans (obligation comptable) | Chiffrement colonnes sensibles (AES-256) |
| 4 | Transport & cantine | Circuits, pointage bus/repas | Exécution du contrat | Élèves | Présences, circuits | Durée de scolarité | — |
| 5 | Surveillance vidéo | Sécurité des locaux (intégration Dahua) | Intérêt légitime (sûreté), dans le respect de la finalité | Toute personne présente | Flux/événements caméras | Durée courte (paramétrable), pas de reconnaissance faciale | Webhooks signés uniquement |
| 6 | Communication parents | SMS, WhatsApp, notifications push | Exécution du contrat (suivi scolaire de l'enfant) | Parents, enseignants | Téléphone, jetons push, messages | Durée de scolarité | Consentement `communication_electronique` tracé (voir § Consentements) |
| 7 | Présence par QR code | Pointage des élèves (jetons courts) | Intérêt légitime (obligation de suivi des présences) | Élèves | Jetons QR à durée de vie < 1 min | Jetons éphémères | — |
| 8 | Détection précoce (EWS) | Repérage des élèves en difficulté | Intérêt légitime (mission éducative) | Élèves | Notes, absences, comportement (scores) | Durée de scolarité | — |
| 9 | Marketplace (centres & offres) | Mise en relation centres/cours | Exécution du contrat | Centres, parents | Identité, coordonnées, réservations | Durée d'activité du compte | — |
| 10 | Sécurité de la plateforme | Authentification, journaux d'audit, anti-fraude | Intérêt légitime (sécurité du service) | Tous les utilisateurs | Identifiants, IP, empreinte appareil, journaux | Journaux d'audit : 1 an ; blocage brute force : 15 min | Chaîne Merkle vérifiée chaque jour ; purge des exports d'audit à 1 an |
| 11 | Exercice des droits (RGPD/18-07) | Portabilité, demande d'effacement, consentements | Obligation légale | Élèves, parents, utilisateurs | Exports de données, historique des demandes et consentements | Demandes et consentements : **conservés sans limite** (preuve de conformité) ; fichiers d'export : **30 jours** | `/rgpd/export-*`, `/rgpd/demande-suppression`, `/rgpd/consentements`, purge quotidienne `edugest:rgpd-retention` |

**Données sensibles** : aucune donnée de santé n'est collectée hors du
module infirmerie/consentement `donnees_sante`. Les données biométriques
(RFID pointage) ne sont utilisées que si l'établissement équipe ses
élèves ; le consentement parental correspondant doit être recueilli et
tracé — voir ci-dessous.

---

## Consentement parental (mineurs)

Les élèves étant mineurs, le consentement du titulaire de l'autorité
parentale est requis pour les traitements qui ne reposent pas sur une
obligation légale ou un contrat. L'API EduGest DZ trace chaque
consentement de façon **immuable** (un retrait = une nouvelle entrée,
jamais d'écrasement) :

```
POST /api/v1/rgpd/consentements   (direction uniquement)
     parent_id, eleve_id, type_consentement, accepte, version
GET  /api/v1/rgpd/consentements?eleve_id=…   (historique)
```

Types gérés : `droit_image`, `sorties_scolaires`, `donnees_sante`,
`transport_scolaire`, `communication_electronique`,
`publication_resultats`.

Chaque enregistrement porte : qui consent (parent), pour qui (élève), qui
a saisi (membre de la direction), la version de la politique, l'IP et la
date. Les consentements sont aussi inscrits dans la chaîne d'audit Merkle.

---

## Rétention automatisée

La commande quotidienne `edugest:rgpd-retention` (planifiée à 04 h 10)
supprime :

- les fichiers d'export RGPD (portabilité, archives) de plus de **30
  jours** — le temps de les télécharger ;
- les exports d'audit de plus de **1 an**.

Les lignes de `demandes_rgpd` et `consentements_rgpd` ne sont **jamais**
supprimées : ce sont les preuves de conformité.

---

## Sous-traitants du sous-traitant (chaîne)

À compléter par chaque établissement selon son niveau de déploiement
(voir [`DEPLOIEMENT.md`](DEPLOIEMENT.md)) :

| Prestataire | Rôle | Données | Localisation |
|-------------|------|---------|--------------|
| Hébergeur (Hostarts DZ / OVH / self-hosted) | Hébergement applicatif et BDD | Toutes | Algérie (niveau 1) / à documenter |
| Twilio | SMS | Numéros de téléphone, contenu SMS | International |
| Meta (WhatsApp Business) | Messagerie | Numéros, messages | International |
| Firebase (FCM) | Notifications push | Jetons push | International |
| Satim | Paiement CIB/Dahabia | Références de paiement | Algérie |

Les transferts hors d'Algérie (Twilio, Meta, Firebase) doivent être
documentés dans la déclaration ANPDP de l'établissement.

---

## Droits des personnes concernées

| Droit | Délai | Outil |
|-------|-------|-------|
| Accès / portabilité | 30 jours | `GET /rgpd/export-eleve/{id}`, `GET /rgpd/export-tenant` |
| Rectification | Immédiat (interface) | Modules élèves/parents |
| Effacement | Demande tracée, traitement par la direction | `POST /rgpd/demande-suppression` (30 jours) |
| Retrait de consentement | Immédiat, historique conservé | `POST /rgpd/consentements` avec `accepte=false` |

Toute demande est journalisée dans `demandes_rgpd` et la chaîne d'audit —
c'est la preuve, en cas de contrôle, que le délai légal a été tenu.
