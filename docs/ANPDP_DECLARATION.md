# Guide Déclaration ANPDP — EduGest DZ
## Loi 18-07 · Autorité Nationale de Protection des Données Personnelles

---

## Pourquoi déclarer ?

La loi algérienne n° 18-07 du 10 juin 2018 exige que tout traitement de
données personnelles soit déclaré à l'ANPDP (autorité opérationnelle
depuis 2023).

EduGest DZ traite des données sensibles :
- Données d'identification d'élèves mineurs
- Données scolaires (notes, bulletins, absences)
- Données biométriques (pointage RFID)
- Données financières (paie, factures)
- Données parentales (téléphone, adresse)

**Sanctions loi 18-07 :** 500 000 DA → 4 000 000 DA + sanctions pénales

---

## Qui déclare ?

**L'établissement scolaire (votre client)** est le responsable du traitement.
**EduGest DZ** est le sous-traitant (processeur de données).

---

## Étapes de déclaration (Niveau 1 et 2)

### 1. Créer un compte sur le portail ANPDP
```
Site : https://www.anpdp.dz (à vérifier)
→ Espace opérateur → Créer un compte
```

### 2. Remplir le formulaire de déclaration

Informations à fournir :
```
Responsable du traitement : [Nom de l'école]
Finalité du traitement    : Gestion scolaire (inscriptions, notes, présences, facturation)
Catégories de données     : Identification, scolaires, financières, biométriques
Destinataires             : Administration interne, parents, enseignants
Durée de conservation     : (détail complet dans REGISTRE_TRAITEMENTS.md)
  - Données élèves     : durée de scolarité + 5 ans
  - Données financières: 10 ans (obligation comptable)
  - Fichiers d'export RGPD : 30 jours (purge automatique quotidienne)
  - Exports d'audit : 1 an (purge automatique quotidienne)
  - Demandes et consentements RGPD : conservés (preuve de conformité)
Sous-traitant             : EduGest DZ (préciser l'hébergeur)
Pays d'hébergement        : Algérie (Niveau 1) / France avec dérogation (Niveau 2)
Mesures de sécurité       : Chiffrement TLS · JWT · 2FA · Chaîne d'audit Merkle vérifiée quotidiennement · Backup quotidien
Consentements parentaux   : API de traçage immuable (REGISTRE_TRAITEMENTS.md)
```

### 3. Mentions légales sur le logiciel

Ajouter dans les paramètres d'EduGest DZ :
```
Politique de confidentialité accessible depuis la page login
Mention : "Données traitées conformément à la loi 18-07 · ANPDP N°XXXX"
```

### 4. Contrat de sous-traitance

Un contrat doit lier l'établissement (client) et EduGest DZ (prestataire).
Contenu obligatoire :
- Objet et durée du traitement
- Nature et finalité
- Obligations du sous-traitant (sécurité, confidentialité)
- Droit de retour des données à la fin du contrat
- Localisation des données

---

## Template de politique de confidentialité (à intégrer dans le logiciel)

```
POLITIQUE DE CONFIDENTIALITÉ — EDUGEST DZ

1. Responsable du traitement
[Nom de l'école] — [Adresse] — [Wilaya]
Email : [directeur@ecole.dz]

2. Données collectées
- Données d'identification (nom, prénom, date de naissance)
- Données scolaires (notes, bulletins, présences, absences)
- Données de contact (téléphone, email des parents)
- Données financières (factures, paiements)

3. Finalités
- Gestion administrative et pédagogique de l'établissement
- Communication avec les parents
- Facturation des frais de scolarité

4. Durée de conservation
- Données actives : durée de scolarité
- Archives : 5 ans après fin de scolarité
- Données financières : 10 ans

5. Droits des personnes concernées
Conformément à la loi 18-07, vous disposez de :
- Droit d'accès à vos données
- Droit de rectification
- Droit à l'effacement
Pour exercer ces droits : [email de contact]

6. Hébergement et sécurité
Données hébergées en Algérie (conformité loi 18-07)
Chiffrement TLS en transit · Chiffrement AES au repos
Déclaration ANPDP N° : [À compléter]

7. Contact
[Nom de l'école] — [Téléphone] — [Email]
```

---

## Argumentaire commercial (pour vendre le Niveau 1)

⚠️ **À ne JAMAIS affirmer** : « déclaré à l'ANPDP », « seul logiciel
conforme », « conformité garantie ». La déclaration ANPDP appartient à
chaque établissement (responsable du traitement) et une conformité ne
se « garantit » pas à la place du client — c'est un argument juridiquement
dangereux et factuellement faux.

Ce qui est **vrai et défendable** :

```
"EduGest DZ fournit les outils de conformité 18-07 : registre des
 traitements pré-rempli, consentements parentaux tracés de façon
 immuable, exports de portabilité, purge automatique des données
 expirées, chaîne d'audit inviolable. En déploiement Niveau 1, les
 données restent hébergées en Algérie. Nous vous accompagnons dans
 votre déclaration à l'ANPDP."
```
