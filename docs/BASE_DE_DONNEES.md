# 🗄️ Base de données — EduGest DZ
## Schéma PostgreSQL 16 · 60+ tables

---

## Conventions

- Toutes les clés primaires sont des **UUID** (non-devinables, non-séquentiels)
- Toutes les tables multi-tenant ont une colonne `tenant_id UUID` avec **RLS PostgreSQL**
- `soft_deletes` sur les modèles critiques (élèves, enseignants, factures)
- `timestamps()` sur toutes les tables (created_at, updated_at)
- Typage strict : VARCHAR avec longueur, pas de TEXT pour les petits champs

---

## Groupes de tables

### 🏢 Tenants & Utilisateurs
| Table | Description |
|-------|-------------|
| `tenants` | Établissements clients (1 ligne = 1 école) |
| `users` | Comptes utilisateurs (admin, enseignant, parent, élève) |
| `roles` | Rôles (admin, enseignant, parent, eleve, super_admin) |
| `permissions` | Permissions granulaires |
| `role_permissions` | Pivot rôle ↔ permission |
| `field_permissions` | Permissions au niveau du champ par tenant |

### 👨‍🎓 Élèves & Parents
| Table | Description |
|-------|-------------|
| `eleves` | Dossiers élèves |
| `parents_eleves` | Parents/tuteurs |
| `parent_eleve` | Pivot élève ↔ parent |
| `inscriptions` | Inscriptions annuelles |

### 📅 Planning & Pédagogie
| Table | Description |
|-------|-------------|
| `groupes` | Classes / groupes |
| `cours` | Définitions des cours (matière, enseignant, horaire) |
| `seances` | Occurrences des cours |
| `presences` | Pointage par séance |
| `absences_journalieres` | Absences journalières |
| `evaluations` | Évaluations (contrôle, devoir, examen) |
| `notes` | Notes individuelles |
| `bulletins` | Bulletins PDF générés |

### 💰 Finance
| Table | Description |
|-------|-------------|
| `factures` | Factures mensuelles |
| `lignes_facture` | Lignes de facture (détail) |
| `paiements` | Paiements enregistrés |
| `depenses` | Dépenses de l'établissement |
| `budgets_previsionnels` | Budget prévisionnel |

### 👨‍🏫 Personnel
| Table | Description |
|-------|-------------|
| `enseignants` | Dossiers enseignants |
| `contrats` | Contrats enseignants |
| `personnel_non_enseignant` | Administratif, gardien, etc. |
| `paies` | Fiches de paie mensuelles |
| `billets` | Billets entrée/retard/sortie/convocation |

### 🚌 Services
| Table | Description |
|-------|-------------|
| `circuits_transport` | Circuits de bus |
| `transport_eleves` | Inscription élève au transport |
| `pointage_bus` | Pointage montée/descente |
| `menus_cantine` | Menus hebdomadaires |
| `inscriptions_cantine` | Abonnements cantine |
| `repas_journaliers` | Pointage repas par élève |
| `articles_stock` | Articles en stock |
| `mouvements_stock` | Entrées/sorties stock |
| `locaux` | Salles, bâtiments |
| `interventions_entretien` | Interventions correctives |
| `entretiens_preventifs` | Maintenance planifiée |

### 🔐 Sécurité
| Table | Description |
|-------|-------------|
| `jwt_blacklist` | Tokens JWT révoqués |
| `trusted_devices` | Appareils de confiance (device fingerprint) |
| `device_challenges` | Codes OTP approbation appareil |
| `security_events` | Journal des événements de sécurité |
| `request_risk_scores` | Historique des scores de risque |
| `honeypot_triggers` | Déclenchements honeypot |
| `canary_tokens` | Tokens de détection de fuite BDD |
| `encrypted_secrets` | Secrets chiffrés (fallback Vault) |
| `audit_log_exports` | Exports d'audit signés HMAC |
| `audit_chain` | Chaîne de blocs Merkle immuable |
| `breach_declarations` | Déclarations d'incidents de sécurité |

### 🔗 Intégrations
| Table | Description |
|-------|-------------|
| `whatsapp_messages` | Messages WhatsApp envoyés/reçus |
| `google_classroom_connexions` | Tokens OAuth2 Google Classroom |
| `device_tokens` | Tokens push Firebase |
| `cameras_config` | Configuration caméras Dahua |
| `alertes_surveillance` | Alertes des caméras |

### 🖥️ LMS & Examens
| Table | Description |
|-------|-------------|
| `lms_cours` | Cours en ligne |
| `lms_chapitres` | Chapitres |
| `lms_lecons` | Leçons (video/PDF/quiz) |
| `lms_inscriptions` | Inscriptions étudiants |
| `lms_progression` | Progression par leçon |
| `examens_officiels` | Examens BEM/BAC |
| `salles_examen` | Salles d'examen |
| `convocations_examen` | Convocations candidats |

### 📚 Bibliothèque & Marketplace
| Table | Description |
|-------|-------------|
| `livres` | Catalogue bibliothèque |
| `prets_livres` | Prêts en cours |
| `profils_marketplace` | Profils centres sur la marketplace |
| `offres_cours` | Offres de cours |
| `reservations_marketplace` | Réservations |

---

## Index importants

Pour les performances, ces index sont créés :

```sql
-- Recherche fréquente par tenant + statut
CREATE INDEX idx_eleves_tenant_statut ON eleves(tenant_id, statut);
CREATE INDEX idx_factures_tenant_statut ON factures(tenant_id, statut);

-- Audit chain — ordre de vérification
CREATE INDEX idx_chain_tenant ON audit_chain(tenant_id, cree_le);

-- Sécurité — lookup rapide
CREATE INDEX idx_blacklist_jti ON jwt_blacklist(jti);
CREATE INDEX idx_trusted_devices_hash ON trusted_devices(device_hash);
CREATE INDEX idx_honeypot_ip ON honeypot_triggers(ip_hash, survenu_le);
```
