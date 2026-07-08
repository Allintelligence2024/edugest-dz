# Plan de Réponse aux Incidents — EduGest DZ
## Procédure officielle · Loi 18-07 · Mise à jour : Juillet 2026

---

## CONTACTS D'URGENCE

| Rôle | Contact | Disponibilité |
|---|---|---|
| Responsable technique | [Ton email] | 24/7 |
| ANPDP (Algérie) | www.anpdp.dz | Heures ouvrables |
| Hostarts DZ (hébergeur) | support@hostarts.dz | 24/7 |

---

## ÉTAPES EN CAS D'INCIDENT

### Étape 1 — DÉTECTER (0-15 minutes)
```
Dashboard sécurité : GET /api/v1/security/dashboard
Vérifier les alertes Telegram
Consulter les logs : docker compose logs app --tail=200
```

### Étape 2 — CONTENIR (15-60 minutes)
```
Si compromission avérée → Verrouillage d'urgence :
POST /api/v1/security/breach/verrouillage-urgence
{ "raison": "...", "confirmer_avec": "VERROUILLAGE_URGENCE_CONFIRME" }

Si 1 seul tenant compromis → Désactiver uniquement ce tenant :
POST /api/v1/super-admin/tenants/{id}/suspendre
```

### Étape 3 — DOCUMENTER (immédiatement)
```
Déclarer l'incident :
POST /api/v1/security/breach/incidents
{
  "type_incident": "data_leak|unauthorized_access|ransomware|...",
  "severite": "low|medium|high|critical",
  "description": "Description détaillée",
  "nb_personnes_affectees": 0,
  "detecte_le": "2026-07-07"
}
```

### Étape 4 — NOTIFIER (dans les 72h — loi 18-07)
```
Délai légal : 72h après détection pour notifier l'ANPDP
Contact : www.anpdp.dz
Informations à fournir :
  - Nature de la violation
  - Données concernées
  - Nombre de personnes affectées
  - Mesures prises
```

### Étape 5 — CORRIGER ET RESTAURER
```
Identifier la cause racine
Appliquer le patch
Vérifier les logs d'audit (intégrité)
Lever le verrouillage si activé :
DELETE /api/v1/security/breach/verrouillage
Rotation JWT secret :
php artisan edugest:jwt-rotate
```

### Étape 6 — INFORMER LES CLIENTS
```
Email aux directeurs d'école concernés avec :
  - Ce qui s'est passé
  - Les données concernées
  - Les mesures prises
  - Ce qu'ils doivent faire
```

---

## DÉLAIS LÉGAUX (loi 18-07)
- Notification ANPDP : **72h** après détection
- Notification des personnes affectées : **sans délai déraisonnable**
- Sanctions si non-respect : 500 000 DA → 4 000 000 DA
